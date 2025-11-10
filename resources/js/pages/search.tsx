import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Search as SearchIcon, FolderOpen, Image, Clock } from 'lucide-react';

export default function Search() {
    return (
        <AppLayout>
            <Head title="Search" />
            
            <div className="p-8 space-y-8">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold text-foreground">Search</h1>
                    <p className="text-muted-foreground mt-1">Find your projects and images</p>
                </div>

                {/* Search Bar */}
                <Card className="p-6">
                    <div className="flex gap-4">
                        <div className="relative flex-1">
                            <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
                            <Input
                                placeholder="Search projects, images, or descriptions..."
                                className="pl-10"
                            />
                        </div>
                        <Button>Search</Button>
                    </div>
                </Card>

                {/* Coming Soon Message */}
                <Card className="p-12">
                    <div className="text-center">
                        <SearchIcon className="h-16 w-16 text-muted-foreground mx-auto mb-4" />
                        <h2 className="text-2xl font-semibold text-foreground mb-2">Search Coming Soon</h2>
                        <p className="text-muted-foreground max-w-md mx-auto">
                            We're building a powerful search feature that will help you quickly find any project or image.
                        </p>
                        <div className="flex items-center justify-center gap-6 mt-8">
                            <div className="text-center">
                                <FolderOpen className="h-8 w-8 text-blue-500 mx-auto mb-2" />
                                <p className="text-sm text-muted-foreground">Search Projects</p>
                            </div>
                            <div className="text-center">
                                <Image className="h-8 w-8 text-green-500 mx-auto mb-2" />
                                <p className="text-sm text-muted-foreground">Find Images</p>
                            </div>
                            <div className="text-center">
                                <Clock className="h-8 w-8 text-purple-500 mx-auto mb-2" />
                                <p className="text-sm text-muted-foreground">Recent Activity</p>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
