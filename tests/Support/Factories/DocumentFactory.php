<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Factories;

use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Faker\Generator;

/**
 * Factory for generating test documents with realistic data
 */
class DocumentFactory
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = FakerFactory::create();
    }

    /**
     * Static factory method for creating test documents
     *
     * @param array<string, mixed> $attributes Document attributes
     * @param array<string, mixed> $metadata Document metadata
     * @param string|null $index Index name
     * @return array<string, mixed>
     */
    public static function createDocument(array $attributes = [], array $metadata = [], ?string $index = null): array
    {
        $document = [
            '_id' => $attributes['id'] ?? '1',
            '_index' => $index ?? 'test_index',
            '_score' => $metadata['_score'] ?? 1.0,
            '_source' => $attributes,
        ];

        // Add any additional metadata
        foreach ($metadata as $key => $value) {
            if ($key !== '_score') {
                $document[$key] = $value;
            }
        }

        return $document;
    }

    /**
     * Create a single document with realistic data
     *
     * @param array $overrides Optional field overrides
     * @return array
     */
    public function create(array $overrides = []): array
    {
        $document = [
            'id' => $this->faker->uuid(),
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'author' => $this->faker->name(),
            'email' => $this->faker->email(),
            'status' => $this->faker->randomElement(['published', 'draft', 'archived']),
            'category' => $this->faker->randomElement(['technology', 'business', 'lifestyle', 'science']),
            'tags' => $this->faker->words(rand(2, 5)),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format(DATE_ATOM),
            'updated_at' => $this->faker->dateTimeBetween('-6 months', 'now')->format(DATE_ATOM),
            'view_count' => $this->faker->numberBetween(0, 10000),
            'is_featured' => $this->faker->boolean(20), // 20% chance of being featured
            'metadata' => [
                'word_count' => $this->faker->numberBetween(100, 5000),
                'reading_time' => $this->faker->numberBetween(1, 20),
                'language' => $this->faker->randomElement(['en', 'es', 'fr', 'de']),
            ],
        ];

        return array_merge($document, $overrides);
    }

    /**
     * Create multiple documents
     *
     * @param int $count Number of documents to create
     * @param array $overrides Optional field overrides applied to all documents
     * @return array
     */
    public function createMany(int $count, array $overrides = []): array
    {
        $documents = [];
        for ($i = 0; $i < $count; $i++) {
            $documents[] = $this->create($overrides);
        }
        return $documents;
    }

    /**
     * Create a user document
     *
     * @param array $overrides
     * @return array
     */
    public function createUser(array $overrides = []): array
    {
        $user = [
            'id' => $this->faker->uuid(),
            'username' => $this->faker->userName(),
            'email' => $this->faker->email(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'bio' => $this->faker->paragraph(),
            'avatar_url' => $this->faker->imageUrl(200, 200, 'people'),
            'location' => $this->faker->city() . ', ' . $this->faker->country(),
            'website' => $this->faker->url(),
            'social_links' => [
                'twitter' => '@' . $this->faker->userName(),
                'linkedin' => $this->faker->url(),
                'github' => $this->faker->userName(),
            ],
            'preferences' => [
                'theme' => $this->faker->randomElement(['light', 'dark']),
                'notifications' => $this->faker->boolean(80),
                'newsletter' => $this->faker->boolean(60),
            ],
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now')->format(DATE_ATOM),
            'last_login' => $this->faker->dateTimeBetween('-1 month', 'now')->format(DATE_ATOM),
            'is_active' => $this->faker->boolean(90),
            'role' => $this->faker->randomElement(['user', 'moderator', 'admin']),
        ];

        return array_merge($user, $overrides);
    }

    /**
     * Create a product document
     *
     * @param array $overrides
     * @return array
     */
    public function createProduct(array $overrides = []): array
    {
        $product = [
            'id' => $this->faker->uuid(),
            'sku' => $this->faker->regexify('[A-Z]{3}-[0-9]{4}'),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraphs(2, true),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => $this->faker->currencyCode(),
            'category' => $this->faker->randomElement(['electronics', 'clothing', 'books', 'home', 'sports']),
            'brand' => $this->faker->company(),
            'in_stock' => $this->faker->boolean(85),
            'stock_quantity' => $this->faker->numberBetween(0, 100),
            'weight' => $this->faker->randomFloat(2, 0.1, 50),
            'dimensions' => [
                'length' => $this->faker->randomFloat(2, 1, 100),
                'width' => $this->faker->randomFloat(2, 1, 100),
                'height' => $this->faker->randomFloat(2, 1, 100),
            ],
            'images' => [
                $this->faker->imageUrl(800, 600, 'technics'),
                $this->faker->imageUrl(800, 600, 'technics'),
            ],
            'rating' => $this->faker->randomFloat(1, 1, 5),
            'review_count' => $this->faker->numberBetween(0, 500),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format(DATE_ATOM),
            'updated_at' => $this->faker->dateTimeBetween('-3 months', 'now')->format(DATE_ATOM),
        ];

        return array_merge($product, $overrides);
    }

    /**
     * Create a document with specific field types for testing
     *
     * @param array $overrides
     * @return array
     */
    public function createWithFieldTypes(array $overrides = []): array
    {
        $document = [
            'string_field' => $this->faker->sentence(),
            'text_field' => $this->faker->paragraphs(2, true),
            'keyword_field' => $this->faker->word(),
            'integer_field' => $this->faker->numberBetween(1, 1000),
            'long_field' => $this->faker->numberBetween(1000000, 9999999999),
            'float_field' => $this->faker->randomFloat(2, 0, 100),
            'double_field' => $this->faker->randomFloat(8, 0, 1000000),
            'boolean_field' => $this->faker->boolean(),
            'date_field' => $this->faker->dateTimeBetween('-1 year', 'now')->format(DATE_ATOM),
            'object_field' => [
                'nested_string' => $this->faker->word(),
                'nested_number' => $this->faker->numberBetween(1, 100),
                'nested_boolean' => $this->faker->boolean(),
            ],
            'array_field' => $this->faker->words(5),
            'geo_point_field' => [
                'lat' => $this->faker->latitude(),
                'lon' => $this->faker->longitude(),
            ],
            'ip_field' => $this->faker->ipv4(),
            'completion_field' => [
                'input' => $this->faker->words(3),
                'weight' => $this->faker->numberBetween(1, 100),
            ],
        ];

        return array_merge($document, $overrides);
    }

    /**
     * Create a document with edge case data for boundary testing
     *
     * @param array $overrides
     * @return array
     */
    public function createEdgeCase(array $overrides = []): array
    {
        $document = [
            'empty_string' => '',
            'null_field' => null,
            'zero_number' => 0,
            'negative_number' => -1,
            'large_number' => PHP_INT_MAX,
            'empty_array' => [],
            'empty_object' => new \stdClass(),
            'unicode_text' => '🚀 Unicode test with émojis and spëcial chars',
            'very_long_text' => str_repeat('Lorem ipsum dolor sit amet. ', 1000),
            'special_chars' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
            'html_content' => '<script>alert("test")</script><p>HTML content</p>',
            'json_string' => '{"nested": "json", "number": 123}',
            'date_edge_cases' => [
                'epoch' => '1970-01-01T00:00:00Z',
                'future' => '2099-12-31T23:59:59Z',
                'invalid_format' => 'not-a-date',
            ],
        ];

        return array_merge($document, $overrides);
    }

    /**
     * Create bulk data for performance testing
     *
     * @param int $count Number of documents to create
     * @param string $type Type of documents ('simple', 'complex', 'mixed')
     * @return array
     */
    public function createBulkData(int $count, string $type = 'simple'): array
    {
        $documents = [];

        for ($i = 0; $i < $count; $i++) {
            switch ($type) {
                case 'simple':
                    $documents[] = [
                        'id' => $i,
                        'title' => "Document {$i}",
                        'content' => $this->faker->sentence(),
                        'created_at' => Carbon::now()->subDays(rand(0, 365))->format(DATE_ATOM),
                    ];
                    break;

                case 'complex':
                    $documents[] = $this->create(['id' => $i]);
                    break;

                case 'mixed':
                    $documents[] = rand(0, 1)
                        ? $this->create(['id' => $i])
                        : $this->createProduct(['id' => $i]);
                    break;
            }
        }

        return $documents;
    }

    /**
     * Create documents with relationships for testing
     *
     * @param int $parentCount Number of parent documents
     * @param int $childrenPerParent Number of children per parent
     * @return array
     */
    public function createWithRelationships(int $parentCount, int $childrenPerParent): array
    {
        $documents = [];

        for ($i = 0; $i < $parentCount; $i++) {
            $parentId = $this->faker->uuid();

            // Create parent document
            $documents[] = [
                'id' => $parentId,
                'type' => 'parent',
                'title' => $this->faker->sentence(),
                'content' => $this->faker->paragraph(),
                'created_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format(DATE_ATOM),
            ];

            // Create child documents
            for ($j = 0; $j < $childrenPerParent; $j++) {
                $documents[] = [
                    'id' => $this->faker->uuid(),
                    'type' => 'child',
                    'parent_id' => $parentId,
                    'title' => $this->faker->sentence(),
                    'content' => $this->faker->paragraph(),
                    'order' => $j + 1,
                    'created_at' => $this->faker->dateTimeBetween('-6 months', 'now')->format(DATE_ATOM),
                ];
            }
        }

        return $documents;
    }
}
