<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

/**
 * Define top-level tags (modules) for grouping in Swagger UI.
 * L5-Swagger will pick up these annotations when scanning `app/`.
 */
#[OA\Tag(name: 'Module 1 - Catalog Metadata', description: 'Public and internal endpoints for catalog metadata (products, categories, attributes).')]
#[OA\Tag(name: 'Module 2 - Search Optimization', description: 'Search-related endpoints and optimization features (search, facets, autocomplete).')]
class Modules
{
}
