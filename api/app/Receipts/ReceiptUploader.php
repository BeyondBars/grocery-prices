<?php

namespace App\Receipts;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Enums\ReceiptStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Stores an uploaded receipt privately and queues it for processing.
 */
class ReceiptUploader
{
    /**
     * @return Receipt|null null when the user already uploaded this exact file
     */
    public function upload(User $user, UploadedFile $file): ?Receipt
    {
        $hash = hash_file('sha256', $file->getRealPath());

        if (!$this->clearPreviousUpload($user, $hash)) {
            return null;
        }

        [$contents, $mime, $extension] = $file->getMimeType() === 'application/pdf'
            ? [$file->getContent(), 'application/pdf', 'pdf']
            : [$this->shrinkPhoto($file), 'image/jpeg', 'jpg'];

        $path = "receipts/{$user->id}/".Str::uuid().".{$extension}";
        Storage::disk(config('receipts.disk'))->put($path, $contents);

        $receipt = Receipt::create([
            'user_id' => $user->id,
            'status' => ReceiptStatus::Uploaded,
            'file_path' => $path,
            'file_mime' => $mime,
            'file_size' => strlen($contents),
            'file_hash' => $hash,
        ]);

        ProcessReceipt::dispatch($receipt);

        return $receipt;
    }

    /**
     * Uploading a receipt that was deleted brings it back as new. Returns false for a duplicate
     * of a receipt the user still has.
     */
    private function clearPreviousUpload(User $user, string $hash): bool
    {
        $previous = Receipt::withTrashed()->whereBelongsTo($user)->where('file_hash', $hash)->first();

        if ($previous && !$previous->trashed()) {
            return false;
        }

        $previous?->forceDelete();

        return true;
    }

    /**
     * Turn the photo upright, shrink it to a sensible size and re-encode it as JPEG. Re-encoding
     * also drops EXIF data, including the GPS location of where the photo was taken.
     */
    private function shrinkPhoto(UploadedFile $file): string
    {
        $max = config('receipts.image_max_px');

        return (string) ImageManager::gd()
            ->read($file->getRealPath())
            ->scaleDown($max, $max)
            ->toJpeg(config('receipts.image_quality'));
    }
}
