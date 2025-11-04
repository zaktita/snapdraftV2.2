import { Component, type ErrorInfo, type ReactNode } from 'react';
import { AlertTriangle, RefreshCw, ArrowLeft } from 'lucide-react';
import { Button } from './ui/button';
import { router } from '@inertiajs/react';

interface Props {
    children: ReactNode;
    wizardName: string;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

/**
 * Wizard-Specific Error Boundary
 * 
 * Catches errors in wizard flows and provides wizard-specific recovery options
 */
export class WizardErrorBoundary extends Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = {
            hasError: false,
            error: null,
        };
    }

    static getDerivedStateFromError(error: Error): Partial<State> {
        return {
            hasError: true,
            error,
        };
    }

    componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        if (import.meta.env.DEV) {
            console.error(
                `Error in ${this.props.wizardName} Wizard:`,
                error,
                errorInfo,
            );
        }

        // TODO: Log to error monitoring service
    }

    handleReset = () => {
        this.setState({
            hasError: false,
            error: null,
        });
        window.location.reload();
    };

    handleGoBack = () => {
        router.visit('/projects');
    };

    render() {
        if (this.state.hasError) {
            return (
                <div className="flex min-h-[60vh] items-center justify-center px-4">
                    <div className="w-full max-w-md text-center">
                        {/* Error Icon */}
                        <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                            <AlertTriangle className="h-8 w-8 text-red-600" />
                        </div>

                        {/* Error Message */}
                        <h2 className="mb-2 text-2xl font-semibold text-neutral-900">
                            {this.props.wizardName} Error
                        </h2>
                        <p className="mb-4 text-sm text-neutral-600">
                            We encountered an error while processing your{' '}
                            {this.props.wizardName.toLowerCase()}.
                        </p>

                        {/* Technical Details */}
                        {this.state.error && (
                            <div className="mx-auto mb-6 max-w-sm rounded-lg bg-neutral-50 p-4 text-left border border-neutral-200">
                                <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide mb-2">
                                    Error Details
                                </p>
                                <p className="text-sm text-neutral-700 font-mono break-words">
                                    {this.state.error.message}
                                </p>
                            </div>
                        )}

                        {/* Action Buttons */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:justify-center">
                            <Button
                                onClick={this.handleReset}
                                variant="default"
                                className="gap-2"
                            >
                                <RefreshCw className="h-4 w-4" />
                                Try Again
                            </Button>
                            <Button
                                onClick={this.handleGoBack}
                                variant="outline"
                                className="gap-2"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Back to Projects
                            </Button>
                        </div>

                        {/* Help Text */}
                        <div className="mt-6 text-left rounded-lg bg-blue-50 p-4 border border-blue-200">
                            <p className="text-xs font-medium text-blue-900 mb-2">
                                Troubleshooting Tips:
                            </p>
                            <ul className="text-xs text-blue-800 space-y-1 list-disc list-inside">
                                <li>Check that all files are in the correct format</li>
                                <li>Ensure you have sufficient credits</li>
                                <li>Try uploading fewer files at once</li>
                                <li>Clear your browser cache and try again</li>
                            </ul>
                        </div>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
