<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Ahs12\Setanjo\Exceptions\DashboardException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class TransferController extends Controller
{
    /**
     * Download everything as a setanjo:export JSON file (?global=1 for global settings only).
     */
    public function export(Request $request): Response
    {
        Artisan::call('setanjo:export', $request->boolean('global') ? ['--global' => true] : []);

        $filename = 'setanjo-'.now()->format('Y-m-d-His').'.json';

        return response(Artisan::output(), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Import a setanjo:export file (upload `file`, or JSON `payload`). dry_run reports counts only.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required_without:payload|file|max:10240',
            'payload' => 'required_without:file|array',
            'dry_run' => 'sometimes|boolean',
        ]);

        $payload = $this->payload($request);

        if (! isset($payload['settings']) || ! is_array($payload['settings'])) {
            throw DashboardException::invalid(__('setanjo::dashboard.invalid_import'));
        }

        $path = tempnam(sys_get_temp_dir(), 'setanjo');
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            $exitCode = Artisan::call('setanjo:import', [
                'path' => $path,
                '--dry-run' => $request->boolean('dry_run'),
            ]);
        } finally {
            @unlink($path);
        }

        return response()->json([
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 422);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return (array) $request->input('payload');
        }

        try {
            $decoded = json_decode((string) $file->get(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw DashboardException::invalid(__('setanjo::dashboard.invalid_import'));
        }

        return is_array($decoded) ? $decoded : [];
    }
}
