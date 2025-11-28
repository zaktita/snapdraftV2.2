import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Loader2, AlertCircle, CheckCircle2 } from 'lucide-react';

interface SimpleWizardProps {
    generatedImage?: string;
    error?: string;
    success?: string;
    prompt?: string;
}

export default function SimpleWizard({ generatedImage, error, success, prompt: initialPrompt }: SimpleWizardProps) {
    const { data, setData, post, processing } = useForm({
        prompt: initialPrompt || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/simple-wizard/generate');
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Simple Wizard', href: '/simple-wizard' }]}>
            <Head title="Simple Text Wizard" />
            
            <div className="container max-w-3xl mx-auto py-8 space-y-8">
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold tracking-tight">Simple Text Wizard</h1>
                    <p className="text-muted-foreground">
                        Direct synchronous generation using Gemini 3 Pro. No queues, immediate results.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Generate Image</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-2">
                                <label htmlFor="prompt" className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                    Prompt
                                </label>
                                <Textarea
                                    id="prompt"
                                    value={data.prompt}
                                    onChange={e => setData('prompt', e.target.value)}
                                    placeholder="Describe the image you want to generate..."
                                    className="min-h-[120px] resize-y"
                                    disabled={processing}
                                />
                            </div>
                            
                            <Button type="submit" disabled={processing || !data.prompt.trim()} className="w-full">
                                {processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Generating (this may take 10-20s)...
                                    </>
                                ) : (
                                    'Generate Image'
                                )}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertTitle>Generation Failed</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {success && (
                    <Alert className="border-green-500 text-green-700 bg-green-50">
                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{success}</AlertDescription>
                    </Alert>
                )}

                {generatedImage && (
                    <Card className="overflow-hidden border-2 border-primary/20">
                        <CardHeader className="bg-muted/50">
                            <CardTitle className="text-lg">Result</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="relative aspect-square w-full bg-muted/20 flex items-center justify-center">
                                <img 
                                    src={generatedImage} 
                                    alt="Generated Result" 
                                    className="w-full h-full object-contain" 
                                />
                            </div>
                            <div className="p-4 bg-muted/30 text-xs text-muted-foreground font-mono break-all">
                                {generatedImage}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
