<?php

namespace App\Services\Metadata;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tries each underlying provider in order until one returns a non-null result.
 * Used to fall back from TMDB → OMDb → TVmaze without polluting the command code
 * with provider-specific branching.
 *
 * fetchById delegates to the FIRST provider — manual id overrides (e.g. the
 * `--tmdb-id` option) are always interpreted in the context of the primary
 * provider, which keeps the existing UX semantics intact.
 */
class CompositeMetadataProvider implements MetadataProvider
{
    /** @var MetadataProvider[] */
    private array $providers;

    /** @param MetadataProvider[] $providers */
    public function __construct(array $providers)
    {
        $this->providers = array_values(array_filter($providers));
    }

    public function search(string $title, string $type, ?int $year = null): ?MetadataResult
    {
        foreach ($this->providers as $provider) {
            try {
                $result = $provider->search($title, $type, $year);
                if ($result !== null) {
                    return $result;
                }
            } catch (Throwable $e) {
                // A misconfigured or temporarily broken provider should not stop the
                // chain — log it and let the next provider try.
                Log::warning('Metadata provider threw during search', [
                    'provider' => $provider::class,
                    'title'    => $title,
                    'type'     => $type,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
        return null;
    }

    public function fetchById(string $externalId, string $type): ?MetadataResult
    {
        if (empty($this->providers)) {
            return null;
        }
        return $this->providers[0]->fetchById($externalId, $type);
    }
}
