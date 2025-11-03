import { useEffect, useState } from 'react';

export interface GenerationProgress {
    project_id: number;
    expected_total: number;
    completed: number;
    failed: number;
    processing: number;
    total: number;
    progress_percentage: number;
    is_complete: boolean;
}

/**
 * Hook to track AI generation progress for a project.
 * Polls the server every 3 seconds while generation is in progress.
 * 
 * @param projectId - The project ID to track progress for
 * @param enabled - Whether to enable polling (default: true)
 * @returns Progress data and loading state
 */
export function useGenerationProgress(projectId: number | null, enabled = true) {
    const [progress, setProgress] = useState<GenerationProgress | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

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
                    setProgress(data);
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

        // Then poll every 3 seconds
        intervalId = setInterval(fetchProgress, 3000);

        return () => {
            isMounted = false;
            if (intervalId) {
                clearInterval(intervalId);
            }
        };
    }, [projectId, enabled]);

    return {
        progress,
        isLoading,
        error,
        isGenerating: progress ? !progress.is_complete && progress.processing > 0 : false,
    };
}
