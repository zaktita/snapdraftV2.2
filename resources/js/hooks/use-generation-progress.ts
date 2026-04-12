import { useEffect, useState, useRef } from 'react';

export interface GenerationProgressPhases {
    brand_analysis: 'pending' | 'processing' | 'completed' | 'failed';
    image_generation: 'pending' | 'processing' | 'completed';
}

export interface GenerationProgress {
    project_id: number;
    expected_total: number;
    completed: number;
    failed: number;
    processing: number;
    total: number;
    progress_percentage: number;
    is_complete: boolean;
    // v2 pipeline optional fields — null when not applicable
    pipeline_version?: string | null;
    cluster_validated?: boolean | null;
    phases?: GenerationProgressPhases | null;
}

/**
 * Hook to track AI generation progress for a project.
 * Polls the server every 2 seconds while generation is in progress.
 * 
 * @param projectId - The project ID to track progress for
 * @param enabled - Whether to enable polling (default: true)
 * @param onComplete - Callback when generation completes
 * @returns Progress data and loading state
 */
export function useGenerationProgress(
    projectId: number | null,
    enabled = true,
    onComplete?: () => void
) {
    const [progress, setProgress] = useState<GenerationProgress | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [wasGenerating, setWasGenerating] = useState(false);

    // Use ref for callback to avoid adding it to dependencies
    const onCompleteRef = useRef(onComplete);

    useEffect(() => {
        onCompleteRef.current = onComplete;
    }, [onComplete]);

    useEffect(() => {
        if (!projectId || !enabled) {
            return;
        }

        let intervalId: NodeJS.Timeout | null = null;
        let isMounted = true;

        const fetchProgress = async () => {
            try {
                setIsLoading(true);
                const response = await fetch(`/projects/${projectId}/generation-progress`, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch progress');
                }

                const data = await response.json();

                if (isMounted) {
                    const isCurrentlyGenerating = !data.is_complete && data.processing > 0;

                    // Check if generation just completed
                    if (wasGenerating && data.is_complete && onCompleteRef.current) {
                        onCompleteRef.current();
                    }

                    setProgress(data);
                    setWasGenerating(isCurrentlyGenerating);
                    setError(null);

                    // Stop polling if generation is complete
                    if (data.is_complete && intervalId) {
                        clearInterval(intervalId);
                    }
                }
            } catch (err) {
                if (isMounted) {
                    setError(err instanceof Error ? err.message : 'Unknown error');
                }
            } finally {
                if (isMounted) {
                    setIsLoading(false);
                }
            }
        };

        // Fetch immediately
        fetchProgress();

        // Then poll every 2 seconds (reduced from 3)
        intervalId = setInterval(fetchProgress, 2000);

        return () => {
            isMounted = false;
            if (intervalId) {
                clearInterval(intervalId);
            }
        };
    }, [projectId, enabled]); // Removed wasGenerating and onComplete from deps to prevent interval restart

    return {
        progress,
        isLoading,
        error,
        isGenerating: progress ? !progress.is_complete && progress.processing > 0 : false,
    };
}
