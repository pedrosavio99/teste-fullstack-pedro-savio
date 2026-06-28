<?php

namespace App\Console\Commands;

use App\Jobs\SyncAffiliatesJob;
use App\Jobs\SyncProductsJob;
use App\Jobs\SyncOrdersPageJob;
use App\Services\FakeStoreClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SyncOrders extends Command
{
    protected $signature = 'orders:sync {--per-page=2 : Quantos pedidos por página/Job}';

    protected $description = 'Sincroniza afiliados, produtos e pedidos da fakestoreapi de forma assíncrona';

    public function handle(FakeStoreClient $client): int
    {
        $perPage = max(1, (int) $this->option('per-page'));

        $this->info('Enfileirando sincronização de afiliados e produtos...');

        // Fase 1: dependências (afiliados e produtos) precisam existir antes
        // dos pedidos por causa das foreign keys. Encadeamos com Bus::chain
        // para garantir que os pedidos só processem depois das dependências.
        $dependencyJobs = [
            new SyncAffiliatesJob(),
            new SyncProductsJob(),
        ];

        // Fase 2: descobrir o total de pedidos e fatiar em páginas.
        // A fakestoreapi não pagina de verdade, então simulamos a paginação
        // por offset/limit sobre o total — a arquitetura escala igual se a
        // API fosse grande (basta o client passar a paginar de fato).
        $totalCarts = count($client->getCarts());
        $totalPages = (int) ceil($totalCarts / $perPage);

        $this->info("Total de pedidos: {$totalCarts} | Páginas: {$totalPages} (de {$perPage} por página)");

        $pageJobs = [];
        for ($page = 1; $page <= $totalPages; $page++) {
            $pageJobs[] = new SyncOrdersPageJob($page, $perPage);
        }

        // Encadeia: primeiro afiliados e produtos, depois os jobs de página.
        Bus::chain(array_merge($dependencyJobs, $pageJobs))->dispatch();

        $this->info('Jobs enfileirados. O worker vai processar em segundo plano.');
        $this->info('Acompanhe com: docker compose logs worker -f');

        return self::SUCCESS;
    }
}