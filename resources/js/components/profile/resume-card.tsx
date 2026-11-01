import { Link, useForm } from '@inertiajs/react';
import { Download, Eye, FilePen, FileText, LoaderCircle, RefreshCw, Trash2, Upload } from 'lucide-react';
import { ChangeEvent, useRef } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { type ResumeSummary } from '@/types/profile';

function formatSize(bytes: number) {
    return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

const statusVariant: Record<ResumeSummary['extraction_status'], 'default' | 'secondary' | 'outline' | 'destructive'> = {
    pending: 'outline',
    extracted: 'default',
    failed: 'destructive',
    reviewed: 'secondary',
};

export function ResumeCard({ resume }: { resume: ResumeSummary | null }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const upload = useForm<{ resume: File | null }>({ resume: null });
    const action = useForm({});

    const onFileChosen = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        upload.setData('resume', file);
        upload.transform(() => ({ resume: file }));
        upload.post(route('seeker.resume.store'), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    return (
        <Card id="resume" className="scroll-mt-20">
            <CardHeader>
                <CardTitle className="text-base">Resume</CardTitle>
                <CardDescription>
                    Upload a PDF or Word (DOCX) file up to 5 MB, or create one from your profile with the resume builder. We read uploaded resumes to
                    suggest profile details; nothing is added until you review it. Only you, and organizations you apply to, can open it.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {resume ? (
                    <div className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex min-w-0 items-start gap-3">
                            <FileText className="text-primary mt-0.5 h-8 w-8 shrink-0" />
                            <div className="min-w-0">
                                <p className="truncate font-medium">{resume.original_name}</p>
                                <p className="text-muted-foreground text-sm">
                                    {formatSize(resume.size_bytes)} · {resume.is_generated ? 'created' : 'uploaded'}{' '}
                                    {new Date(resume.uploaded_at).toLocaleDateString()}
                                </p>
                                {resume.is_generated ? (
                                    <Badge variant="secondary" className="mt-1">
                                        Made with the resume builder
                                    </Badge>
                                ) : (
                                    <Badge variant={statusVariant[resume.extraction_status]} className="mt-1">
                                        {resume.extraction_label}
                                    </Badge>
                                )}
                                {resume.extraction_error && <p className="text-muted-foreground mt-1 text-sm">{resume.extraction_error}</p>}
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {resume.is_pdf && (
                                <Button variant="outline" size="sm" asChild>
                                    <a href={route('seeker.resume.file', { resume: resume.id, preview: 1 })} target="_blank" rel="noreferrer">
                                        <Eye className="h-4 w-4" /> Preview
                                    </a>
                                </Button>
                            )}
                            <Button variant="outline" size="sm" asChild>
                                <a href={route('seeker.resume.file', resume.id)}>
                                    <Download className="h-4 w-4" /> Download
                                </a>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => action.delete(route('seeker.resume.destroy', resume.id), { preserveScroll: true })}
                                disabled={action.processing}
                            >
                                <Trash2 className="h-4 w-4" /> Remove
                            </Button>
                        </div>
                    </div>
                ) : (
                    <p className="text-muted-foreground text-sm">No resume uploaded yet.</p>
                )}

                {resume?.extraction_status === 'extracted' && (
                    <div className="bg-primary/5 flex flex-col gap-2 rounded-lg p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm">We found details in your resume. Review them before they're added to your profile.</p>
                        <Button size="sm" asChild>
                            <Link href={route('seeker.resume.review', resume.id)}>Review details</Link>
                        </Button>
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    <input
                        ref={fileInput}
                        type="file"
                        accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        className="hidden"
                        onChange={onFileChosen}
                    />
                    <Button onClick={() => fileInput.current?.click()} disabled={upload.processing}>
                        {upload.processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                        {upload.processing ? 'Uploading and reading…' : resume ? 'Upload a new resume' : 'Upload resume'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('seeker.resume-builder.edit')}>
                            <FilePen className="h-4 w-4" />{' '}
                            {resume?.is_generated ? 'Update with the resume builder' : 'Create with the resume builder'}
                        </Link>
                    </Button>
                    {resume?.extraction_status === 'failed' && (
                        <Button
                            variant="outline"
                            onClick={() => action.post(route('seeker.resume.extract', resume.id), { preserveScroll: true })}
                            disabled={action.processing}
                        >
                            <RefreshCw className="h-4 w-4" /> Try reading again
                        </Button>
                    )}
                </div>
                <InputError message={upload.errors.resume} />
            </CardContent>
        </Card>
    );
}
