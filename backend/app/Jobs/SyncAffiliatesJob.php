<?php

namespace App\Jobs;

use App\Models\Affiliate;
use App\Services\FakeStoreClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAffiliatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;          // até 3 tentativas
    public int $backoff = 5;        // espera 5s entre tentativas (backoff)

    public function handle(FakeStoreClient $client): void
    {
        $users = $client->getUsers();

        $rows = [];
        foreach ($users as $u) {
            $name = trim(($u['name']['firstname'] ?? '') . ' ' . ($u['name']['lastname'] ?? ''));

            $rows[] = [
                'id'       => $u['id'],
                'name'     => $name !== '' ? $name : ($u['username'] ?? 'Afiliado ' . $u['id']),
                'email'    => $u['email'] ?? null,
                'username' => $u['username'] ?? null,
                'phone'    => $u['phone'] ?? null,
                'status'   => 'active',
            ];
        }

        // upsert idempotente: insere novos, atualiza existentes pela PK (id),
        // sem gerar duplicatas mesmo rodando várias vezes.
        if (! empty($rows)) {
            Affiliate::upsert($rows, ['id'], ['name', 'email', 'username', 'phone', 'status']);
        }
    }
}