import { GoogleGenAI, Type } from "@google/genai";

// ── Configuration ────────────────────────────────────────────────────────────

const CONFIG = {
  models: {
    clustering: "gemini-3.1-pro-preview",
    captionMatch: "gemini-3-flash-preview",
    imageGeneration: "gemini-3.1-flash-image-preview",
  },
  retry: {
    maxAttempts: 3,
    baseDelayMs: 2000,
  },
} as const;

let aiInstance: GoogleGenAI | null = null;

function getAI() {
  if (!aiInstance) {
    const apiKey = process.env.GEMINI_API_KEY;
    if (!apiKey) {
      throw new Error("GEMINI_API_KEY is not defined. Please check your environment variables.");
    }
    aiInstance = new GoogleGenAI({ apiKey });
  }
  return aiInstance;
}

// ── Types ────────────────────────────────────────────────────────────────────

export interface ImageInput {
  data: string;
  mimeType: string;
}

export interface Cluster {
  name: string;
  tags: string[];
  reason: string;
  imageIndices: number[];
  dominantColor: string;
  typographyStyle: string;
  compositionType: string;
  backgroundTreatment: string;
  palette: string[];
  textPlacement: string;
}

export interface ClusterResponse {
  globalAnalysis: string;
  globalRules: string[];
  clusters: Cluster[];
}

export interface CsvRow {
  title: string;
  caption: string;
  format: string;
}

export interface BatchMatchResult {
  rowIndex: number;
  clusterIndex: number;
  overlayText: string;
  explanation: string;
}

export interface PromptBatchItem {
  rowIndex: number;
  title: string;
  clusterIndex: number;
  referenceImageIndices: number[];
  generationPrompt: string;
  overlayText: string;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function toInlinePart(img: ImageInput) {
  const raw = img.data.includes(",") ? img.data.split(",")[1] : img.data;
  return { inlineData: { data: raw, mimeType: img.mimeType } };
}

async function withRetry<T>(
  fn: () => Promise<T>,
  { maxAttempts = CONFIG.retry.maxAttempts, baseDelayMs = CONFIG.retry.baseDelayMs } = {},
): Promise<T> {
  let lastError: Error | undefined;
  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      return await fn();
    } catch (err) {
      lastError = err instanceof Error ? err : new Error(String(err));
      if (attempt < maxAttempts) {
        await new Promise((r) => setTimeout(r, baseDelayMs * attempt));
      }
    }
  }
  throw lastError;
}

function pickTopReferenceImages(cluster: Cluster, count: number): number[] {
  return cluster.imageIndices.slice(0, count);
}

// ── Phase 1: Clustering ──────────────────────────────────────────────────────

export async function clusterImages(images: ImageInput[]): Promise<ClusterResponse> {
  const ai = getAI();
  const imageParts = images.map(toInlinePart);

  const prompt = `You are an expert social media strategist and visual analyst.
Analyze these ${images.length} images and group them into clusters with extreme precision.

CRITERIA FOR CLUSTERING (evaluate in this order):
1. Visual Aesthetic: Color palettes, lighting, filters, and overall "vibe".
2. Content Strategy: Subject matter, intent (educational, promotional, lifestyle, event, testimonial, product showcase), and emotional resonance.
3. Branding Consistency: Logo placement, specific typography usage, and brand-specific graphic elements.
4. Layout & Composition: Use of white space, grid structures, text-to-image ratio, and visual hierarchy.

INSTRUCTIONS:
- Provide a "globalAnalysis" summarizing the overall brand identity found across all images.
- Provide "globalRules": 4-6 actionable constraints that apply across every cluster (e.g. "Never place text directly over photographs", "Always use the brand footer lockup").
- Group every image into a cluster. Each image MUST belong to exactly one cluster.
- CLUSTER SIZE CONSTRAINT: Each cluster MUST contain exactly 2 or 3 images.
- If an image is an outlier, pair it with its most similar counterpart.
- For each cluster:
  - "dominantColor" MUST be a hex code (e.g. "#EA6E2A"). If multiple dominant colors, pick the single most prominent.
  - "palette": array of 3-5 hex color strings found in this cluster's images (e.g. ["#EA6E2A", "#1A1A1A", "#FFFFFF"]).
  - "typographyStyle": be specific (e.g. "Extrabold uppercase sans-serif ~8% canvas height" not just "Bold Sans-Serif").
  - "compositionType": describe spatial layout precisely (e.g. "Centered single headline at 40% height, logo bottom-center at 90%").
  - "backgroundTreatment": describe what the background is (e.g. "Flat solid #F5EFE6", "Full-bleed photo with 60% dark overlay", "Gradient from #1A1A1A to #2D2D2D").
  - "textPlacement": describe where and how text sits relative to images/background (e.g. "Text on flat color zone only, never over photo").`;

  const response = await ai.models.generateContent({
    model: CONFIG.models.clustering,
    contents: {
      parts: [...imageParts, { text: prompt }],
    },
    config: {
      responseMimeType: "application/json",
      responseSchema: {
        type: Type.OBJECT,
        properties: {
          globalAnalysis: { type: Type.STRING },
          globalRules: { type: Type.ARRAY, items: { type: Type.STRING } },
          clusters: {
            type: Type.ARRAY,
            items: {
              type: Type.OBJECT,
              properties: {
                name: { type: Type.STRING },
                tags: { type: Type.ARRAY, items: { type: Type.STRING } },
                reason: { type: Type.STRING },
                imageIndices: { type: Type.ARRAY, items: { type: Type.INTEGER } },
                dominantColor: { type: Type.STRING },
                typographyStyle: { type: Type.STRING },
                compositionType: { type: Type.STRING },
                backgroundTreatment: { type: Type.STRING },
                palette: { type: Type.ARRAY, items: { type: Type.STRING } },
                textPlacement: { type: Type.STRING },
              },
              required: [
                "name", "tags", "reason", "imageIndices", "dominantColor",
                "typographyStyle", "compositionType", "backgroundTreatment",
                "palette", "textPlacement",
              ],
            },
          },
        },
        required: ["globalAnalysis", "globalRules", "clusters"],
      },
    },
  });

  if (!response.text) {
    throw new Error("No response from Gemini during clustering");
  }

  const result = JSON.parse(response.text) as ClusterResponse;
  validateClusters(result, images.length);
  return result;
}

function validateClusters(result: ClusterResponse, imageCount: number): void {
  const seen = new Set<number>();
  for (const [i, cluster] of result.clusters.entries()) {
    if (cluster.imageIndices.length < 2 || cluster.imageIndices.length > 3) {
      throw new Error(`Cluster ${i} ("${cluster.name}") has ${cluster.imageIndices.length} images — must be 2 or 3.`);
    }
    for (const idx of cluster.imageIndices) {
      if (idx < 0 || idx >= imageCount) {
        throw new Error(`Cluster ${i} references out-of-bounds image index ${idx}.`);
      }
      if (seen.has(idx)) {
        throw new Error(`Image index ${idx} assigned to multiple clusters.`);
      }
      seen.add(idx);
    }
  }
  for (let i = 0; i < imageCount; i++) {
    if (!seen.has(i)) {
      throw new Error(`Image index ${i} was not assigned to any cluster.`);
    }
  }
}

// ── Phase 2: Batch Caption Matching + Prompt Building ────────────────────────

export async function buildPromptBatch(
  clusterResult: ClusterResponse,
  csvRows: CsvRow[],
): Promise<PromptBatchItem[]> {
  const ai = getAI();

  const clusterContext = clusterResult.clusters
    .map(
      (c, i) =>
        `Cluster ${i}: "${c.name}"
  Tags: ${c.tags.join(", ")}
  Dominant: ${c.dominantColor} | Palette: ${c.palette.join(", ")}
  Typography: ${c.typographyStyle}
  Composition: ${c.compositionType}
  Background: ${c.backgroundTreatment}
  Text placement: ${c.textPlacement}
  Reason: ${c.reason}`,
    )
    .join("\n---\n");

  const rowsContext = csvRows
    .map((r, i) => `Row ${i} | title: ${r.title} | format: ${r.format} | caption: ${r.caption}`)
    .join("\n");

  const prompt = `Match each CSV row to exactly one cluster using this priority:
1. Vibe/style fit → 2. Typography/composition fit → 3. Content-strategy intent fit

Clusters:
${clusterContext}

CSV rows:
${rowsContext}

For each row, also generate short on-image overlay text derived from the caption (punchy, 3-8 words).

Rules:
- Include every row exactly once.
- Choose only valid cluster indices (0 to ${clusterResult.clusters.length - 1}).
- overlay_text must be usable directly on a social media image.`;

  const response = await ai.models.generateContent({
    model: CONFIG.models.captionMatch,
    contents: { parts: [{ text: prompt }] },
    config: {
      responseMimeType: "application/json",
      responseSchema: {
        type: Type.OBJECT,
        properties: {
          matches: {
            type: Type.ARRAY,
            items: {
              type: Type.OBJECT,
              properties: {
                rowIndex: { type: Type.INTEGER },
                clusterIndex: { type: Type.INTEGER },
                overlayText: { type: Type.STRING },
                explanation: { type: Type.STRING },
              },
              required: ["rowIndex", "clusterIndex", "overlayText", "explanation"],
            },
          },
        },
        required: ["matches"],
      },
    },
  });

  if (!response.text) {
    throw new Error("No response from Gemini during caption matching");
  }

  const { matches } = JSON.parse(response.text) as { matches: BatchMatchResult[] };
  const matchByRow = new Map(matches.map((m) => [m.rowIndex, m]));

  return csvRows.map((row, i) => {
    const match = matchByRow.get(i);
    if (!match) throw new Error(`No match returned for CSV row ${i}`);

    const cluster = clusterResult.clusters[match.clusterIndex];
    if (!cluster) throw new Error(`Invalid clusterIndex ${match.clusterIndex} for row ${i}`);

    const overlayText = match.overlayText || row.caption;
    const refIndices = pickTopReferenceImages(cluster, 2);

    return {
      rowIndex: i,
      title: row.title,
      clusterIndex: match.clusterIndex,
      referenceImageIndices: refIndices,
      overlayText,
      generationPrompt: buildGenerationPrompt(overlayText, cluster, clusterResult.globalRules),
    };
  });
}

function buildGenerationPrompt(overlayText: string, cluster: Cluster, globalRules: string[]): string {
  const rulesBlock = globalRules.length > 0
    ? globalRules.map((r, i) => `${i + 1}. ${r}`).join("\n")
    : "Maintain strict visual consistency with the reference images.";

  return `Generate a square 1:1 social media post image that matches the EXACT style of the reference images.
Study the references carefully — your output must mirror their background, typography, layout, spacing, and mood.

STYLE ANCHOR:
- Background: ${cluster.backgroundTreatment}
- Dominant Color: ${cluster.dominantColor}
- Palette: ${cluster.palette.join(", ")}
- Typography: ${cluster.typographyStyle}
- Text Placement: ${cluster.textPlacement}

LAYOUT:
- Composition: ${cluster.compositionType}
- Reproduce the spatial hierarchy, negative-space behavior, and padding from the references precisely.

CONTENT:
- Headline text to render on the image: "${overlayText}"
- Cluster style: ${cluster.name}

GLOBAL BRAND RULES:
${rulesBlock}

EXCLUSIONS:
- No colors outside the palette above unless clearly present in references.
- No generic stock-photo aesthetics.
- No elements, textures, or decorative shapes not visible in references.
- No watermarks, signatures, or artifacts.`;
}

// ── Phase 3: Image Generation ────────────────────────────────────────────────

export async function generateImage(
  promptItem: PromptBatchItem,
  allImages: ImageInput[],
): Promise<{ rowIndex: number; dataUrl: string }> {
  const ai = getAI();

  const refParts = promptItem.referenceImageIndices
    .filter((idx) => idx >= 0 && idx < allImages.length)
    .map((idx) => toInlinePart(allImages[idx]));

  const result = await withRetry(async () => {
    const response = await ai.models.generateContent({
      model: CONFIG.models.imageGeneration,
      contents: {
        parts: [...refParts, { text: promptItem.generationPrompt }],
      },
      config: {
        responseModalities: ["IMAGE"],
        imageConfig: {
          aspectRatio: "1:1",
        },
      },
    });

    const imagePart = response.candidates?.[0]?.content?.parts.find(
      (p: any) => p.inlineData,
    );
    if (!imagePart?.inlineData?.data) {
      throw new Error("Gemini did not return image data");
    }
    return imagePart.inlineData.data as string;
  });

  return {
    rowIndex: promptItem.rowIndex,
    dataUrl: `data:image/png;base64,${result}`,
  };
}

// ── Convenience: generate all images from a prompt batch ─────────────────────

export async function generateAllImages(
  promptBatch: PromptBatchItem[],
  allImages: ImageInput[],
  onProgress?: (completed: number, total: number, result: { rowIndex: number; dataUrl?: string; error?: string }) => void,
): Promise<{ rowIndex: number; dataUrl?: string; error?: string }[]> {
  const results: { rowIndex: number; dataUrl?: string; error?: string }[] = [];

  for (const item of promptBatch) {
    try {
      const result = await generateImage(item, allImages);
      results.push(result);
      onProgress?.(results.length, promptBatch.length, result);
    } catch (err) {
      const errorResult = {
        rowIndex: item.rowIndex,
        error: err instanceof Error ? err.message : String(err),
      };
      results.push(errorResult);
      onProgress?.(results.length, promptBatch.length, errorResult);
    }
  }

  return results;
}
