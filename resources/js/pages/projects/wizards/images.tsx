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
// import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Image } from 'lucide-react';
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
        title: 'Images Wizard',
        href: '/projects/create/images',
    },
];

export default function ImagesWizard() {
    const [currentStep, setCurrentStep] = useState(1);
    const [projectName, setProjectName] = useState('');
    const [referenceImages, setReferenceImages] = useState<File[]>([]);
    const [contentDescription, setContentDescription] = useState('');

    const steps = [
        {
            number: 1,
            title: 'Project Setup',
            description: 'Name your project',
        },
        {
            number: 2,
            title: 'Reference Images',
            description: 'Upload brand references',
        },
        {
            number: 3,
            title: 'Content Details',
            description: 'Describe what to generate',
        },
    ];

    const handleReferenceImagesUpload = (
        e: React.ChangeEvent<HTMLInputElement>,
    ) => {
        if (e.target.files) {
            setReferenceImages(Array.from(e.target.files));
        }
    };

    const canProceedToStep2 = projectName.trim().length > 0;
    const canProceedToStep3 = referenceImages.length >= 5;
    const canSubmit =
        projectName.trim().length > 0 &&
        referenceImages.length >= 5 &&
        contentDescription.trim().length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Images Wizard - New Project" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/projects/create">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">Images Wizard</h1>
                        <p className="text-sm text-muted-foreground">
                            Generate visuals from brand reference images
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
                    {/* Step 1: Project Setup */}
                    {currentStep === 1 && (
                        <Card className="mx-auto max-w-2xl">
                            <CardHeader>
                                <CardTitle>Project Setup</CardTitle>
                                <CardDescription>
                                    Give your project a descriptive name
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="project-name">
                                        Project Name *
                                    </Label>
                                    <Input
                                        id="project-name"
                                        placeholder="e.g., Summer Campaign 2025"
                                        value={projectName}
                                        onChange={(e) =>
                                            setProjectName(e.target.value)
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Choose a name that helps you identify
                                        this project later
                                    </p>
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
                                    style. AI will analyze colors, typography,
                                    and composition patterns.
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
                                            onChange={
                                                handleReferenceImagesUpload
                                            }
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
                                        What makes good reference images:
                                    </p>
                                    <ul className="space-y-1 text-sm text-muted-foreground">
                                        <li>
                                            • Images that showcase your brand's
                                            visual identity
                                        </li>
                                        <li>
                                            • High-quality files (JPG, PNG, or
                                            WebP)
                                        </li>
                                        <li>
                                            • Variety in layouts and compositions
                                        </li>
                                        <li>
                                            • Examples of typography and color
                                            usage
                                        </li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Step 3: Content Details */}
                    {currentStep === 3 && (
                        <Card className="mx-auto max-w-2xl">
                            <CardHeader>
                                <CardTitle>Content Details</CardTitle>
                                <CardDescription>
                                    Describe what you want to generate
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="content-description">
                                        What do you want to create? *
                                    </Label>
                                    <Textarea
                                        id="content-description"
                                        placeholder="e.g., Social media posts for our summer sale campaign featuring our new product line..."
                                        value={contentDescription}
                                        onChange={(
                                            e: React.ChangeEvent<HTMLTextAreaElement>,
                                        ) => setContentDescription(e.target.value)}
                                        rows={6}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Be specific about the type of content,
                                        purpose, and any key elements to include
                                    </p>
                                </div>

                                <div className="rounded-lg border bg-muted/30 p-4">
                                    <p className="mb-2 text-sm font-medium">
                                        Example descriptions:
                                    </p>
                                    <ul className="space-y-2 text-sm text-muted-foreground">
                                        <li>
                                            • "Instagram posts promoting our new
                                            eco-friendly product line with nature
                                            themes"
                                        </li>
                                        <li>
                                            • "LinkedIn banner showcasing our
                                            company's mission and team culture"
                                        </li>
                                        <li>
                                            • "Facebook ads for our holiday sale
                                            with festive elements"
                                        </li>
                                    </ul>
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
                        <Button disabled={!canSubmit}>
                            Start Generation
                            <ArrowRight className="ml-2 size-4" />
                        </Button>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
