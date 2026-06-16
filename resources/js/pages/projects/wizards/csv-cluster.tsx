import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChangeEvent, DragEvent, FormEvent, useCallback, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { AlertCircle, ArrowLeft, Layers, Loader2, Upload } from 'lucide-react';
import { cn } from '@/lib/utils';

type ColumnMapping = Record<string, string>;
type CsvRow = Record<string, string>;

const MAPPING_OPTIONS = [
    'Product Title',
    'Image Prompt',
    'Format',
    'Ignore this column',
] as const;

export default function CsvClusterWizard() {
    const page = usePage<{
        auth?: { user?: { credits_remaining?: number } };
        errors?: Record<string, string>;
    }>();

    const [step, setStep] = useState(1);
    const [projectName, setProjectName] = useState('');
    const [csvFile, setCsvFile] = useState<File | null>(null);
    const [csvData, setCsvData] = useState<CsvRow[]>([]);
    const [headers, setHeaders] = useState<string[]>([]);
    const [columnMappings, setColumnMappings] = useState<ColumnMapping>({});
    const [selectedRows, setSelectedRows] = useState<Set<number>>(new Set());
    const [referenceFiles, setReferenceFiles] = useState<File[]>([]);
    const [referencePreviews, setReferencePreviews] = useState<string[]>([]);
    const [resolutionMultiplier, setResolutionMultiplier] = useState('1');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const refInputRef = useRef<HTMLInputElement>(null);

    const creditsRemaining = page.props.auth?.user?.credits_remaining ?? 0;
    const globalError = page.props.errors?.credits ?? page.props.errors?.csv_file ?? page.props.errors?.error;

    const parseCsvFile = useCallback((file: File) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const text = (e.target?.result as string)?.replace(/^\uFEFF/, '') ?? '';
            const lines = text.split('\n').map((l) => l.trim()).filter(Boolean);
            if (lines.length < 2) return;

            const parsedHeaders = lines[0].split(',').map((h) => h.trim().replace(/^"|"$/g, ''));
            const rows: CsvRow[] = [];

            for (let i = 1; i < lines.length; i++) {
                const values = lines[i].match(/(".*?"|[^",\s]+)(?=\s*,|\s*$)/g) ?? lines[i].split(',');
                const row: CsvRow = {};
                parsedHeaders.forEach((h, idx) => {
                    row[h] = (values[idx] ?? '').replace(/^"|"$/g, '').trim();
                });
                rows.push(row);
            }

            const mappings: ColumnMapping = {};
            parsedHeaders.forEach((h, idx) => {
                const lower = h.toLowerCase();
                if (lower.includes('title') || lower.includes('name')) {
                    mappings[h] = 'Product Title';
                } else if (lower.includes('caption') || lower.includes('prompt')) {
                    mappings[h] = 'Image Prompt';
                } else if (lower.includes('format')) {
                    mappings[h] = 'Format';
                } else {
                    mappings[h] = idx === 0 ? 'Product Title' : 'Ignore this column';
                }
            });

            setHeaders(parsedHeaders);
            setCsvData(rows);
            setColumnMappings(mappings);
            setSelectedRows(new Set(rows.map((_, i) => i)));
            setCsvFile(file);
            setStep(2);
        };
        reader.readAsText(file);
    }, []);

    const handleCsvUpload = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) parseCsvFile(file);
    };

    const handleRefs = (files: FileList | null) => {
        if (!files) return;
        const arr = Array.from(files).slice(0, 10);
        setReferenceFiles(arr);
        setReferencePreviews(arr.map((f) => URL.createObjectURL(f)));
    };

    const buildFilteredCsvFile = (): File => {
        const selected = Array.from(selectedRows).sort((a, b) => a - b);
        const filtered = selected.map((i) => csvData[i]).filter(Boolean);
        const content = [
            headers.join(','),
            ...filtered.map((row) =>
                headers.map((h) => `"${(row[h] ?? '').replace(/"/g, '""')}"`).join(','),
            ),
        ].join('\n');
        return new File([content], csvFile?.name ?? 'data.csv', { type: 'text/csv' });
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!projectName.trim() || !csvFile || referenceFiles.length < 2 || selectedRows.size === 0) return;

        const requiredCredits = selectedRows.size * Number(resolutionMultiplier);
        if (requiredCredits > creditsRemaining) return;

        setIsSubmitting(true);

        const fd = new FormData();
        fd.append('project_name', projectName.trim());
        fd.append('csv_file', buildFilteredCsvFile());
        fd.append('column_mappings', JSON.stringify(columnMappings));
        fd.append('resolution_multiplier', resolutionMultiplier);
        referenceFiles.forEach((f) => fd.append('reference_images[]', f));

        router.post('/projects/wizards/csv-cluster', fd, {
            forceFormData: true,
            onFinish: () => setIsSubmitting(false),
        });
    };

    const selectedCount = selectedRows.size;
    const requiredCredits = selectedCount * Number(resolutionMultiplier);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'CSV Cluster Wizard', href: '/projects/create/csv-cluster' },
            ]}
        >
            <Head title="CSV Cluster Wizard" />

            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <Layers className="h-6 w-6 text-primary" />
                            <h1 className="text-2xl font-bold">CSV Cluster Wizard</h1>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            4-step pipeline: cluster &amp; DNA → match captions → generate prompts → generate images
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/projects/create/csv">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Classic CSV wizard
                        </Link>
                    </Button>
                </div>

                {globalError && (
                    <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        {globalError}
                    </div>
                )}

                <div className="flex gap-2">
                    {[1, 2, 3, 4].map((s) => (
                        <Badge key={s} variant={step === s ? 'default' : 'outline'}>
                            Step {s}
                        </Badge>
                    ))}
                </div>

                {step === 1 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Project name</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="project_name">Name</Label>
                                <Input
                                    id="project_name"
                                    value={projectName}
                                    onChange={(e) => setProjectName(e.target.value)}
                                    placeholder="e.g. Q2 Social Campaign"
                                />
                            </div>
                            <Button onClick={() => projectName.trim() && setStep(2)} disabled={!projectName.trim()}>
                                Continue
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {step === 2 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Upload CSV</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {!csvData.length ? (
                                <div
                                    className={cn(
                                        'flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-12',
                                        dragOver ? 'border-primary bg-primary/5' : 'border-muted-foreground/30',
                                    )}
                                    onClick={() => fileInputRef.current?.click()}
                                    onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                                    onDragLeave={() => setDragOver(false)}
                                    onDrop={(e) => {
                                        e.preventDefault();
                                        setDragOver(false);
                                        const file = e.dataTransfer.files[0];
                                        if (file) parseCsvFile(file);
                                    }}
                                >
                                    <Upload className="text-muted-foreground mb-3 h-10 w-10" />
                                    <p className="font-medium">Drop CSV or click to browse</p>
                                    <input ref={fileInputRef} type="file" accept=".csv,text/csv" className="hidden" onChange={handleCsvUpload} />
                                </div>
                            ) : (
                                <>
                                    <p className="text-muted-foreground text-sm">{csvData.length} rows · map columns and select rows to generate</p>
                                    <div className="overflow-x-auto rounded-md border">
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/50">
                                                <tr>
                                                    <th className="p-2 w-8" />
                                                    {headers.map((h) => (
                                                        <th key={h} className="p-2 text-left">
                                                            <div className="font-medium">{h}</div>
                                                            <select
                                                                className="text-muted-foreground mt-1 w-full bg-transparent text-xs"
                                                                value={columnMappings[h] ?? 'Ignore this column'}
                                                                onChange={(e) => setColumnMappings({ ...columnMappings, [h]: e.target.value })}
                                                            >
                                                                {MAPPING_OPTIONS.map((o) => (
                                                                    <option key={o} value={o}>{o}</option>
                                                                ))}
                                                            </select>
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {csvData.map((row, i) => (
                                                    <tr key={i} className="border-t">
                                                        <td className="p-2 text-center">
                                                            <input
                                                                type="checkbox"
                                                                checked={selectedRows.has(i)}
                                                                onChange={() => {
                                                                    const next = new Set(selectedRows);
                                                                    if (next.has(i)) next.delete(i);
                                                                    else next.add(i);
                                                                    setSelectedRows(next);
                                                                }}
                                                            />
                                                        </td>
                                                        {headers.map((h) => (
                                                            <td key={h} className="p-2 max-w-[200px] truncate">{row[h]}</td>
                                                        ))}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button variant="outline" onClick={() => setStep(1)}>Back</Button>
                                        <Button onClick={() => setStep(3)} disabled={selectedRows.size === 0}>
                                            Continue ({selectedRows.size} rows)
                                        </Button>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}

                {step === 3 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Brand reference images</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                Upload 2–10 past posts. Gemini clusters them into template families before generating.
                            </p>
                            <div
                                className="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-10"
                                onClick={() => refInputRef.current?.click()}
                            >
                                <Upload className="text-muted-foreground mb-2 h-8 w-8" />
                                <p className="text-sm">Click to add images</p>
                                <input
                                    ref={refInputRef}
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    className="hidden"
                                    onChange={(e) => handleRefs(e.target.files)}
                                />
                            </div>
                            {referencePreviews.length > 0 && (
                                <div className="grid grid-cols-5 gap-2">
                                    {referencePreviews.map((src, i) => (
                                        <img key={src} src={src} alt="" className="aspect-square rounded border object-cover" />
                                    ))}
                                </div>
                            )}
                            <div className="flex gap-2">
                                <Button variant="outline" onClick={() => setStep(2)}>Back</Button>
                                <Button onClick={() => setStep(4)} disabled={referenceFiles.length < 2}>
                                    Continue
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {step === 4 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Review &amp; generate</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="grid gap-2 text-sm sm:grid-cols-2">
                                    <div><span className="text-muted-foreground">Project:</span> {projectName}</div>
                                    <div><span className="text-muted-foreground">Rows:</span> {selectedCount}</div>
                                    <div><span className="text-muted-foreground">References:</span> {referenceFiles.length}</div>
                                    <div><span className="text-muted-foreground">Credits:</span> {requiredCredits} / {creditsRemaining}</div>
                                </div>
                                <div className="space-y-2 max-w-xs">
                                    <Label>Resolution</Label>
                                    <Select value={resolutionMultiplier} onValueChange={setResolutionMultiplier}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">1x</SelectItem>
                                            <SelectItem value="2">2x</SelectItem>
                                            <SelectItem value="4">4x</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex gap-2">
                                    <Button type="button" variant="outline" onClick={() => setStep(3)}>Back</Button>
                                    <Button
                                        type="submit"
                                        disabled={isSubmitting || requiredCredits > creditsRemaining || referenceFiles.length < 2}
                                    >
                                        {isSubmitting ? (
                                            <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Starting pipeline…</>
                                        ) : (
                                            'Start 4-step generation'
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
