<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams media stored in Google Drive through the app, so files stay private to the shared drive
 * while authenticated users can preview and download them. Nothing is cached on the server's disk.
 */
class MediaController extends Controller
{
    public function __construct(protected GoogleDriveService $drive) {}

    public function file(Request $request, Media $media): StreamedResponse|RedirectResponse
    {
        if ($media->isLink()) {
            return redirect()->away((string) $media->external_url);
        }

        abort_unless($media->drive_file_id, 404);

        try {
            $download = $this->drive->downloadStream($media->drive_file_id);
        } catch (\Throwable $e) {
            report($e);
            abort(502, __('No se pudo obtener el archivo desde Google Drive.'));
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        $body = $download->toPsrResponse()->getBody();

        return response()->stream(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(262144);
                flush();
            }
        }, 200, array_filter([
            'Content-Type' => $media->mime_type ?: ($download->header('Content-Type') ?: 'application/octet-stream'),
            'Content-Length' => $download->header('Content-Length') ?: null,
            'Content-Disposition' => $disposition.'; filename="'.addcslashes($media->name, '"\\').'"; filename*=UTF-8\'\''.rawurlencode($media->name),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }

    public function thumbnail(Request $request, Media $media): Response
    {
        abort_if($media->isLink() || ! $media->drive_file_id, 404);

        $size = min(max((int) $request->query('size', 400), 64), 1600);

        try {
            $thumbnail = $this->drive->thumbnail($media->drive_file_id, $size);
        } catch (\Throwable $e) {
            report($e);
            $thumbnail = null;
        }

        abort_if($thumbnail === null, 404);

        return response($thumbnail['content'], 200, [
            'Content-Type' => $thumbnail['mime'],
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
