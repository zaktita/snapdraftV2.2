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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Lightbulb } from 'lucide-react';
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
        title: 'Text Wizard',
        href: '/projects/create/text',
    },
];

const formatOptions = [
    { value: 'instagram-post', label: 'Instagram Post (1080x1080)' },
    { value: 'instagram-story', label: 'Instagram Story (1080x1920)' },
    { value: 'facebook-post', label: 'Facebook Post (1200x630)' },
    { value: 'facebook-ad', label: 'Facebook Ad (1200x628)' },
    { value: 'linkedin-post', label: 'LinkedIn Post (1200x627)' },
    { value: 'linkedin-banner', label: 'LinkedIn Banner (1584x396)' },
    { value: 'twitter-post', label: 'Twitter Post (1200x675)' },
    { value: 'youtube-thumbnail', label: 'YouTube Thumbnail (1280x720)' },
];

export default function TextWizard() {
    const [currentStep, setCurrentStep] = useState(1);
    const [projectName, setProjectName] = useState('');
    const [ideaDescription, setIdeaDescription] = useState('');
    const [selectedFormat, setSelectedFormat] = useState('');
    const [referenceImages, setReferenceImages] = useState<File[]>([]);

    const steps = [
        {
            number: 1,
            title: 'Project Setup',
            description: 'Name your project',
        },
        {
            number: 2,
            title: 'Describe Your Idea',
            description: 'What do you want to create?',
        },
        {
            number: 3,
            title: 'Optional References',
            description: 'Add style references',
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
    const canProceedToStep3 =
        ideaDescription.trim().length > 0 && selectedFormat.length > 0;
    const canSubmit =
        projectName.trim().length > 0 &&
        ideaDescription.trim().length > 0 &&
        selectedFormat.length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Text Wizard - New Project" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/projects/create">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">Text Wizard</h1>
                        <p className="text-sm text-muted-foreground">
                            Generate visuals from your text description
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
                                        placeholder="e.g., Brand Awareness Campaign"
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

                    {/* Step 2: Describe Your Idea */}
                    {currentStep === 2 && (
                        <Card className="mx-auto max-w-2xl">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Lightbulb className="size-5" />
                                    Describe Your Idea
                                </CardTitle>
                                <CardDescription>
                                    Tell us what you want to create and we'll
                                    generate it for you
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="idea-description">
                                        What do you want to create? *
                                    </Label>
                                    <Textarea
                                        id="idea-description"
                                        placeholder="e.g., A vibrant social media post showcasing our new eco-friendly water bottle with nature elements in the background..."
                                        value={ideaDescription}
                                        onChange={(
                                            e: React.ChangeEvent<HTMLTextAreaElement>,
                                        ) => setIdeaDescription(e.target.value)}
                                        rows={8}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Be as detailed as possible - mention
                                        colors, style, mood, key elements, etc.
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="format-select">
                                        Output Format *
                                    </Label>
                                    <Select
                                        value={selectedFormat}
                                        onValueChange={setSelectedFormat}
                                    >
                                        <SelectTrigger id="format-select">
                                            <SelectValue placeholder="Select a format..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {formatOptions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="rounded-lg border bg-muted/30 p-4">
                                    <p className="mb-2 text-sm font-medium">
                                        Tips for better results:
                                    </p>
                                    <ul className="space-y-1 text-sm text-muted-foreground">
                                        <li>
                                            • Describe the visual style you want
                                            (minimalist, bold, elegant, etc.)
                                        </li>
                                        <li>
                                            • Mention specific colors or color
                                            palettes
                                        </li>
                                        <li>
                                            • Include key elements that must
                                            appear
                                        </li>
                                        <li>
                                            • Describe the mood or feeling you
                                            want to convey
                                        </li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Step 3: Optional References */}
                    {currentStep === 3 && (
                        <Card className="mx-auto max-w-2xl">
                            <CardHeader>
                                <CardTitle>
                                    Reference Images (Optional)
                                </CardTitle>
                                <CardDescription>
                                    Upload reference images to guide the style
                                    (optional but recommended)
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="reference-upload">
                                        Style Reference Images (Optional)
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

                                <div className="rounded-lg border bg-blue-500/10 p-4">
                                    <p className="text-sm text-muted-foreground">
                                        Reference images help guide the visual
                                        style. If you have examples of the look
                                        and feel you want, upload them here.
                                        Otherwise, we'll generate based on your
                                        text description.
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
