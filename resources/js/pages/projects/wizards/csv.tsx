import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    FileSpreadsheet,
    Image,
    Upload,
} from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Projects',
        href: '/projects',
    },
    {
        title: 'CSV Wizard',
        href: '/projects/create/csv',
    },
];

export default function CSVWizard() {
    const [currentStep, setCurrentStep] = useState(1);
    const [csvFile, setCsvFile] = useState<File | null>(null);
    const [referenceImages, setReferenceImages] = useState<File[]>([]);
    const [productImages, setProductImages] = useState<File[]>([]);

    const { data, setData, post, processing, errors } = useForm({
        csv_file: null as File | null,
        reference_images: [] as File[],
        product_images: [] as File[],
    });

    const steps = [
        {
            number: 1,
            title: 'Upload CSV',
            description: 'Provide your content data',
        },
        {
            number: 2,
            title: 'Reference Images',
            description: 'Upload brand style references',
        },
        {
            number: 3,
            title: 'Product Images (Optional)',
            description: 'Add product overlays',
        },
    ];

    const handleCsvUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            const file = e.target.files[0];
            setCsvFile(file);
            setData('csv_file', file);
        }
    };

    const handleReferenceImagesUpload = (
        e: React.ChangeEvent<HTMLInputElement>,
    ) => {
        if (e.target.files) {
            const files = Array.from(e.target.files);
            setReferenceImages(files);
            setData('reference_images', files);
        }
    };

    const handleProductImagesUpload = (
        e: React.ChangeEvent<HTMLInputElement>,
    ) => {
        if (e.target.files) {
            const files = Array.from(e.target.files);
            setProductImages(files);
            setData('product_images', files);
        }
    };

    const canProceedToStep2 = csvFile !== null;
    const canProceedToStep3 = referenceImages.length >= 5;
    const canSubmit = csvFile && referenceImages.length >= 5;

    const handleSubmit = () => {
        post('/projects/wizards/csv', {
            preserveScroll: true,
            onSuccess: () => {
                // Will redirect to project page on success
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="CSV Wizard - New Project" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/projects/create">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">CSV Wizard</h1>
                        <p className="text-sm text-muted-foreground">
                            Generate brand-consistent visuals from CSV data
                        </p>
                    </div>
                </div>

                {/* Progress Steps */}
                <div className="flex items-center justify-center gap-2">
                    {steps.map((step, idx) => (
                        <div key={step.number} className="flex items-center">
                            <div className="flex flex-col items-center gap-2">
                                <div
                                    className={`flex size-10 items-center justify-center rounded-full border-2 font-semibold transition-colors ${
                                        currentStep === step.number
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : currentStep > step.number
                                              ? 'border-primary bg-primary/10 text-primary'
                                              : 'border-muted-foreground/30 bg-background text-muted-foreground'
                                    }`}
                                >
                                    {step.number}
                                </div>
                                <div className="text-center">
                                    <p
                                        className={`text-sm font-medium ${currentStep === step.number ? 'text-foreground' : 'text-muted-foreground'}`}
                                    >
                                        {step.title}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {step.description}
                                    </p>
                                </div>
                            </div>
                            {idx < steps.length - 1 && (
                                <div
                                    className={`mx-4 h-px w-16 ${currentStep > step.number ? 'bg-primary' : 'bg-muted-foreground/30'}`}
                                />
                            )}
                        </div>
                    ))}
                </div>

                {/* Step Content */}
                <div className="flex-1">
                    {/* Step 1: CSV Upload */}
                    {currentStep === 1 && (
                        <Card className="mx-auto max-w-2xl">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileSpreadsheet className="size-5" />
                                    Upload CSV File
                                </CardTitle>
                                <CardDescription>
                                    Your CSV must include columns: title,
                                    description, format
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="csv-upload">
                                        CSV File *
                                    </Label>
                                    <div className="flex flex-col gap-4">
                                        <Input
                                            id="csv-upload"
                                            type="file"
                                            accept=".csv"
                                            onChange={handleCsvUpload}
                                        />
                                        <InputError message={errors.csv_file} />
                                        {csvFile && (
                                            <div className="rounded-lg border bg-muted/30 p-3">
                                                <p className="text-sm font-medium">
                                                    {csvFile.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {(
                                                        csvFile.size / 1024
                                                    ).toFixed(2)}{' '}
                                                    KB
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="rounded-lg border bg-muted/30 p-4">
                                    <p className="mb-2 text-sm font-medium">
                                        CSV Format Example:
                                    </p>
                                    <pre className="overflow-x-auto text-xs">
                                        {`title,description,format
"Product Launch","New smartphone feature",Instagram Post
"Summer Sale","20% off all items",Facebook Ad
"Brand Story","Our mission and values",LinkedIn Banner`}
                                    </pre>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Step 2: Reference Images */}
                    {currentStep === 2 && (
                        <Card className="mx-auto max-w-2xl">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Image className="size-5" />
                                    Upload Reference Images
                                </CardTitle>
                                <CardDescription>
                                    Upload 5-10 images that represent your brand
                                    style
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="reference-upload">
                                        Brand Reference Images * (5-10 required)
                                    </Label>
                                    <div className="flex flex-col gap-4">
                                        <Input
                                            id="reference-upload"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            multiple
                                            onChange={handleReferenceImagesUpload}
                                        />
                                        <InputError
                                            message={errors.reference_images}
                                        />
                                        {referenceImages.length > 0 && (
                                            <div className="space-y-2">
                                                <p className="text-sm font-medium">
                                                    {referenceImages.length}{' '}
                                                    image(s) selected
                                                </p>
                                                <div className="grid max-h-48 gap-2 overflow-y-auto">
                                                    {referenceImages.map(
                                                        (file, idx) => (
                                                            <div
                                                                key={idx}
                                                                className="rounded-lg border bg-muted/30 p-2 text-sm"
                                                            >
                                                                {file.name}
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="rounded-lg border bg-muted/30 p-4">
                                    <p className="mb-2 text-sm font-medium">
                                        Tips for Reference Images:
                                    </p>
                                    <ul className="space-y-1 text-sm text-muted-foreground">
                                        <li>
                                            • Include examples of your brand's
                                            visual style
                                        </li>
                                        <li>
                                            • Use high-quality images (JPG, PNG,
                                            or WebP)
                                        </li>
                                        <li>
                                            • Show variety in composition and
                                            layout
                                        </li>
                                        <li>
                                            • AI will analyze colors, typography,
                                            and patterns
                                        </li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Step 3: Product Images */}
                    {currentStep === 3 && (
                        <Card className="mx-auto max-w-2xl">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Upload className="size-5" />
                                    Product Images (Optional)
                                </CardTitle>
                                <CardDescription>
                                    Add product images to overlay on generated
                                    visuals (up to 5)
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="product-upload">
                                        Product Images (Optional)
                                    </Label>
                                    <div className="flex flex-col gap-4">
                                        <Input
                                            id="product-upload"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            multiple
                                            onChange={handleProductImagesUpload}
                                        />
                                        {productImages.length > 0 && (
                                            <div className="space-y-2">
                                                <p className="text-sm font-medium">
                                                    {productImages.length}{' '}
                                                    image(s) selected
                                                </p>
                                                <div className="grid max-h-48 gap-2 overflow-y-auto">
                                                    {productImages.map(
                                                        (file, idx) => (
                                                            <div
                                                                key={idx}
                                                                className="rounded-lg border bg-muted/30 p-2 text-sm"
                                                            >
                                                                {file.name}
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="rounded-lg border bg-blue-500/10 p-4">
                                    <p className="text-sm text-muted-foreground">
                                        Product images will be overlaid on the
                                        generated visuals. This step is optional
                                        - you can skip it if you don't need
                                        product overlays.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Navigation Buttons */}
                <div className="flex items-center justify-between border-t pt-4">
                    <Button
                        variant="outline"
                        onClick={() =>
                            setCurrentStep((prev) => Math.max(1, prev - 1))
                        }
                        disabled={currentStep === 1}
                    >
                        <ArrowLeft className="mr-2 size-4" />
                        Previous
                    </Button>

                    {currentStep < 3 ? (
                        <Button
                            onClick={() => setCurrentStep((prev) => prev + 1)}
                            disabled={
                                (currentStep === 1 && !canProceedToStep2) ||
                                (currentStep === 2 && !canProceedToStep3)
                            }
                        >
                            Next
                            <ArrowRight className="ml-2 size-4" />
                        </Button>
                    ) : (
                        <Button
                            disabled={!canSubmit || processing}
                            onClick={handleSubmit}
                        >
                            {processing ? 'Processing...' : 'Start Generation'}
                            <ArrowRight className="ml-2 size-4" />
                        </Button>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
