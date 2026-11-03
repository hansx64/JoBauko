<?php

namespace App\Services\Resumes;

use App\Enums\ResumeExtractionStatus;
use App\Models\Resume;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ResumeService
{
    public function __construct(
        private readonly ResumeTextExtractor $textExtractor,
        private readonly ResumeDataExtractor $dataExtractor,
    ) {}

    /**
     * Store a new resume as the user's current one, then read it.
     */
    public function upload(User $user, UploadedFile $file): Resume
    {
        $extension = $file->getMimeType() === ResumeTextExtractor::PDF ? 'pdf' : 'docx';
        $path = $file->storeAs("resumes/{$user->id}", Str::uuid().'.'.$extension, Uploads::name());

        $resume = $this->makeCurrent($user, [
            'disk_path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'extraction_status' => ResumeExtractionStatus::Pending,
        ]);

        $this->extract($resume);

        return $resume->refresh();
    }

    /**
     * Store a PDF made by the resume builder as the user's current resume.
     * Its contents already come from the profile, so there is nothing to read
     * back or review.
     */
    public function storeGenerated(User $user, string $pdf, string $fileName): Resume
    {
        $path = "resumes/{$user->id}/".Str::uuid().'.pdf';
        Uploads::disk()->put($path, $pdf);

        return $this->makeCurrent($user, [
            'disk_path' => $path,
            'original_name' => $fileName,
            'mime_type' => ResumeTextExtractor::PDF,
            'size_bytes' => strlen($pdf),
            'is_generated' => true,
            'extraction_status' => ResumeExtractionStatus::Reviewed,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeCurrent(User $user, array $attributes): Resume
    {
        return DB::transaction(function () use ($user, $attributes) {
            $previous = $user->resumes()->where('is_current', true)->get();

            $user->resumes()->update(['is_current' => false]);

            $resume = $user->resumes()->create([...$attributes, 'is_current' => true]);

            // Previous versions are kept only while an application still
            // refers to them; otherwise they are deleted (data minimization).
            $previous->each(fn (Resume $old) => $this->deleteIfUnused($old));

            return $resume;
        });
    }

    /**
     * Read the resume and store suggested profile data for review.
     */
    public function extract(Resume $resume): void
    {
        // Read from a temporary copy, since uploads may be stored off this server (Google Drive).
        $copy = tempnam(sys_get_temp_dir(), 'resume');

        try {
            file_put_contents($copy, Uploads::disk()->get($resume->disk_path));
            $text = $this->textExtractor->extract($copy, $resume->mime_type);
        } catch (Throwable $e) {
            Log::warning('Resume text extraction failed', ['resume_id' => $resume->id, 'error' => $e->getMessage()]);

            $this->markFailed($resume, 'We could not read this file. It may be damaged or password-protected.');

            return;
        } finally {
            @unlink($copy);
        }

        if (mb_strlen($text) < 30) {
            $this->markFailed($resume, 'No readable text was found. Scanned or image-only resumes cannot be read automatically.');

            return;
        }

        $resume->update([
            'extracted_text' => $text,
            'extracted_data' => $this->dataExtractor->extract($text),
            'extraction_status' => ResumeExtractionStatus::Extracted,
            'reviewed_at' => null,
        ]);
    }

    /**
     * Remove a resume. One attached to an application is kept for that
     * application and only stops being the user's current resume.
     */
    public function remove(Resume $resume): void
    {
        $resume->update(['is_current' => false]);

        $this->deleteIfUnused($resume);
    }

    private function deleteIfUnused(Resume $resume): void
    {
        if (DB::table('applications')->where('resume_id', $resume->id)->exists()) {
            return;
        }

        Uploads::disk()->delete($resume->disk_path);
        $resume->delete();
    }

    private function markFailed(Resume $resume, string $reason): void
    {
        $resume->update([
            'extracted_text' => null,
            'extracted_data' => ['error' => $reason],
            'extraction_status' => ResumeExtractionStatus::Failed,
        ]);
    }
}
