<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\ClientFile;
use App\Domain\Team\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Plans land on the private disk — never the public one, because a training plan is health data
 * and a guessable URL is not a permission system (docs/START-TUTAJ.md §11).
 */
class AttachClientFile
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $actor, Client $client, UploadedFile $upload): ClientFile
    {
        $file = $client->files()->create([
            'name' => $upload->getClientOriginalName(),
            'extension' => mb_strtoupper($upload->getClientOriginalExtension() ?: 'plik'),
            'path' => $upload->store('klienci/'.$client->getKey(), 'local'),
            'size' => $upload->getSize(),
        ]);

        $this->log->record($actor, 'Wgrał plik', $client->name.' · '.$file->name);

        return $file;
    }
}
