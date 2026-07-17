import { Component, type ErrorInfo, type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { Sentry } from '@/lib/sentry';
import { Button } from './ui/button';
import {
    AlertTriangle,
    Home,
    RefreshCw,
    Mail,
    ChevronDown,
    ChevronUp,
} from 'lucide-react';

interface Props {
    children: ReactNode;
    fallback?: (error: Error, errorInfo: ErrorInfo) => ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
    errorInfo: ErrorInfo | null;
    showDetails: boolean;
}

/**
 * Global Error Boundary Component
 * 
 * Catches JavaScript errors anywhere in the React component tree,
 * logs errors, and displays a fallback UI instead of crashing the app.
 * 
 * @example
 * ```tsx
 * <ErrorBoundary>
 *   <App />
 * </ErrorBoundary>
 * ```
 */
export class ErrorBoundary extends Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = {
            hasError: false,
            error: null,
            errorInfo: null,
            showDetails: false,
        };
    }

    static getDerivedStateFromError(error: Error): Partial<State> {
        return {
            hasError: true,
            error,
        };
    }

    componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        // Log error to console in development
        if (import.meta.env.DEV) {
            console.error('ErrorBoundary caught an error:', error, errorInfo);
        } else {
            Sentry.captureException(error, {
                contexts: { react: { componentStack: errorInfo.componentStack } },
            });
        }

        this.setState({
            errorInfo,
        });
    }

    handleReset = () => {
        this.setState({
            hasError: false,
            error: null,
            errorInfo: null,
            showDetails: false,
        });
    };

    handleGoHome = () => {
        this.handleReset();
        router.visit('/dashboard');
    };

    handleReload = () => {
        this.handleReset();
        window.location.reload();
    };

    toggleDetails = () => {
        this.setState((prev) => ({ showDetails: !prev.showDetails }));
    };

    render() {
        if (this.state.hasError) {
            // Custom fallback UI if provided
            if (this.props.fallback && this.state.error && this.state.errorInfo) {
                return this.props.fallback(this.state.error, this.state.errorInfo);
            }

            // Default fallback UI
            return (
                <div className="flex min-h-screen items-center justify-center bg-neutral-50 px-4 py-12 sm:px-6 lg:px-8">
                    <div className="w-full max-w-2xl">
                        <div className="rounded-xl border border-neutral-200 bg-white p-8 shadow-sm">
                            {/* Icon & Title */}
                            <div className="flex items-center gap-4 mb-6">
                                <div className="flex-shrink-0">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                                        <AlertTriangle className="h-6 w-6 text-red-600" />
                                    </div>
                                </div>
                                <div>
                                    <h1 className="text-2xl font-semibold text-neutral-900">
                                        Something went wrong
                                    </h1>
                                    <p className="mt-1 text-sm text-neutral-500">
                                        We've encountered an unexpected error
                                    </p>
                                </div>
                            </div>

                            {/* Error Message */}
                            <div className="mb-6 rounded-lg bg-neutral-50 p-4 border border-neutral-200">
                                <p className="text-sm font-medium text-neutral-900 mb-2">
                                    Error Details:
                                </p>
                                <p className="text-sm text-neutral-700 font-mono">
                                    {this.state.error?.message || 'Unknown error'}
                                </p>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex flex-wrap gap-3 mb-6">
                                <Button
                                    onClick={this.handleReload}
                                    variant="default"
                                    className="gap-2"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    Reload Page
                                </Button>
                                <Button
                                    onClick={this.handleGoHome}
                                    variant="outline"
                                    className="gap-2"
                                >
                                    <Home className="h-4 w-4" />
                                    Go to Dashboard
                                </Button>
                                <Button
                                    onClick={() => window.location.href = 'mailto:support@snapdraft.com?subject=Error Report'}
                                    variant="outline"
                                    className="gap-2"
                                >
                                    <Mail className="h-4 w-4" />
                                    Contact Support
                                </Button>
                            </div>

                            {/* Technical Details (Collapsible) */}
                            {import.meta.env.DEV && this.state.errorInfo && (
                                <div className="border-t border-neutral-200 pt-6">
                                    <button
                                        onClick={this.toggleDetails}
                                        className="flex w-full items-center justify-between text-left text-sm font-medium text-neutral-700 hover:text-neutral-900"
                                    >
                                        <span>Technical Details (Development Mode)</span>
                                        {this.state.showDetails ? (
                                            <ChevronUp className="h-4 w-4" />
                                        ) : (
                                            <ChevronDown className="h-4 w-4" />
                                        )}
                                    </button>
                                    
                                    {this.state.showDetails && (
                                        <div className="mt-4 rounded-lg bg-neutral-900 p-4 overflow-auto max-h-96">
                                            <pre className="text-xs text-neutral-100 whitespace-pre-wrap">
                                                <div className="mb-4">
                                                    <strong>Error:</strong>
                                                    {'\n'}
                                                    {this.state.error?.toString()}
                                                </div>
                                                <div>
                                                    <strong>Component Stack:</strong>
                                                    {'\n'}
                                                    {this.state.errorInfo.componentStack}
                                                </div>
                                            </pre>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Help Text */}
                            <div className="mt-6 rounded-lg bg-blue-50 p-4 border border-blue-200">
                                <p className="text-sm text-blue-800">
                                    <strong>What you can do:</strong>
                                </p>
                                <ul className="mt-2 list-disc list-inside text-sm text-blue-700 space-y-1">
                                    <li>Try reloading the page</li>
                                    <li>Clear your browser cache and cookies</li>
                                    <li>Check your internet connection</li>
                                    <li>If the problem persists, contact our support team</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
