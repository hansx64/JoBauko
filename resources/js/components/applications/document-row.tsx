import { Download, Eye, FileText } from 'lucide-react';

import { Button } from '@/components/ui/button';

export function DocumentRow({
    label,
    name,
    meta,
    url,
    previewUrl,
}: {
    label: string;
    name: string;
    meta?: string;
    url: string;
    previewUrl: string | null;
}) {
    return (
        <li className="flex flex-wrap items-center gap-3 p-3">
            <FileText className="text-muted-foreground h-5 w-5 shrink-0" />
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">{label}</p>
                <p className="text-muted-foreground truncate text-xs">
                    {name}
                    {meta && ` · ${meta}`}
                </p>
            </div>
            <div className="flex gap-1">
                {previewUrl && (
                    <Button asChild size="sm" variant="ghost">
                        <a href={previewUrl} target="_blank" rel="noreferrer">
                            <Eye className="h-4 w-4" /> View
                        </a>
                    </Button>
                )}
                <Button asChild size="sm" variant="ghost">
                    <a href={url}>
                        <Download className="h-4 w-4" /> Download
                    </a>
                </Button>
            </div>
        </li>
    );
}
