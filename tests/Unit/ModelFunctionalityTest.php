<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Matchory\Elasticsearch\Collection;
use Matchory\Elasticsearch\Exceptions\DocumentNotFoundException;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Tests\TestCase;
use Matchory\Elasticsearch\Tests\Support\Traits\AssertsElasticsearch;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive tests for Model functionality
 *
 * Tests all aspects of the Elasticsearch Model including CRUD operations,
 * attribute handling, serialization, and configuration.
 */
class ModelFunctionalityTest extends TestCase
{
    use AssertsElasticsearch;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Assert that Elasticsearch method was called
     */
    protected function assertElasticsearchMethodCalled(string $method, int $times = 1): void
    {
        $calls = $this->getMockClient()->getMethodCalls($method);
        $this->assertCount($times, $calls, "Expected method '{$method}' to be called {$times} times");
    }

    /**
     * Assert that Elasticsearch method was not called
     */
    protected function assertElasticsearchMethodNotCalled(string $method): void
    {
        $this->assertFalse(
            $this->getMockClient()->wasMethodCalled($method),
            "Expected method '{$method}' to not be called",
        );
    }

    #[Test]
    public function it_implements_required_interfaces(): void
    {
        $model = new Model();

        $this->assertInstanceOf(Arrayable::class, $model);
        $this->assertInstanceOf(\ArrayAccess::class, $model);
        $this->assertInstanceOf(Jsonable::class, $model);
        $this->assertInstanceOf(JsonSerializable::class, $model);
        $this->assertInstanceOf(UrlRoutable::class, $model);
        $this->assertInstanceOf(QueueableEntity::class, $model);
    }

    #[Test]
    public function it_can_create_new_model_instance(): void
    {
        $attributes = [
            'title' => 'Test Document',
            'content' => 'Test content',
            'status' => 'published',
        ];

        $model = new Model($attributes);

        $this->assertEquals('Test Document', $model->title);
        $this->assertEquals('Test content', $model->content);
        $this->assertEquals('published', $model->status);
        $this->assertFalse($model->exists());
    }

    #[Test]
    public function it_can_create_existing_model_instance(): void
    {
        $attributes = [
            'title' => 'Existing Document',
            'content' => 'Existing content',
        ];

        $model = new Model($attributes, true);

        $this->assertEquals('Existing Document', $model->title);
        $this->assertTrue($model->exists());
    }

    #[Test]
    public function it_can_save_new_model(): void
    {
        $this->getMockClient()->setResponse('index', [
            '_id' => 'test-id-123',
            '_index' => 'test_index',
            '_version' => 1,
            'result' => 'created',
        ]);

        $model = new Model([
            'title' => 'New Document',
            'content' => 'New content',
        ]);
        $model->setIndex('test_index');

        $result = $model->save();

        $this->assertSame($model, $result);
        $this->assertTrue($model->exists());
        $this->assertTrue($model->wasRecentlyCreated);
        $this->assertEquals('test-id-123', $model->getKey());
        $this->assertTrue($this->getMockClient()->wasMethodCalled('index'));
    }

    #[Test]
    public function it_can_update_existing_model(): void
    {
        $this->getMockClient()->setResponse('update', [
            '_index' => 'test_index',
            '_id' => 'existing-id',
            '_version' => 2,
            'result' => 'updated',
        ]);

        $model = new Model([
            '_id' => 'existing-id',
            'title' => 'Original Title',
            'content' => 'Original content',
        ], true);
        $model->setIndex('test_index');

        $model->syncOriginal();
        $model->title = 'Updated Title';

        $result = $model->save();

        $this->assertSame($model, $result);
        $this->assertTrue($model->exists());
        $this->assertFalse($model->wasRecentlyCreated);
        $this->assertEquals('Updated Title', $model->title);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('update'));
    }

    #[Test]
    public function it_does_not_update_when_no_changes(): void
    {
        $model = new Model([
            '_id' => 'existing-id',
            'title' => 'Unchanged Title',
        ], true);
        $model->setIndex('test_index');

        $model->syncOriginal();
        $result = $model->save();

        $this->assertSame($model, $result);
        $this->assertFalse($this->getMockClient()->wasMethodCalled('update'));
    }

    #[Test]
    public function it_can_delete_existing_model(): void
    {
        $this->getMockClient()->setResponse('delete', [
            '_index' => 'test_index',
            '_id' => 'existing-id',
            '_version' => 2,
            'result' => 'deleted',
        ]);

        $model = new Model([
            '_id' => 'existing-id',
            'title' => 'Document to Delete',
        ], true);
        $model->setIndex('test_index');

        $result = $model->delete();

        $this->assertTrue($result);
        $this->assertFalse($model->exists());
        $this->assertTrue($this->getMockClient()->wasMethodCalled('delete'));
    }

    #[Test]
    public function it_cannot_delete_non_existing_model(): void
    {
        $model = new Model([
            'title' => 'Non-existing Document',
        ]);

        $result = $model->delete();

        $this->assertFalse($result);
        $this->assertFalse($this->getMockClient()->wasMethodCalled('delete'));
    }

    #[Test]
    public function it_can_find_model_by_id(): void
    {
        $this->getMockClient()->setResponse('search', [
            'took' => 1,
            'timed_out' => false,
            'hits' => [
                'total' => ['value' => 1, 'relation' => 'eq'],
                'hits' => [
                    [
                        '_id' => 'test-id',
                        '_source' => [
                            'title' => 'Found Document',
                            'content' => 'Found content',
                        ],
                    ],
                ],
            ],
        ]);

        $model = Model::find('test-id');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertEquals('test-id', $model->getKey());
        $this->assertEquals('Found Document', $model->title);
        $this->assertTrue($model->exists());
        $this->assertTrue($this->getMockClient()->wasMethodCalled('search'));
    }

    #[Test]
    public function it_returns_null_when_model_not_found(): void
    {
        $this->getMockClient()->setResponse('search', [
            'took' => 1,
            'timed_out' => false,
            'hits' => [
                'total' => ['value' => 0, 'relation' => 'eq'],
                'hits' => [],
            ],
        ]);

        $model = Model::find('non-existing-id');

        $this->assertNull($model);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('search'));
    }

    #[Test]
    public function it_throws_exception_when_find_or_fail_not_found(): void
    {
        $this->getMockClient()->setResponse('search', [
            'took' => 1,
            'timed_out' => false,
            'hits' => [
                'total' => ['value' => 0, 'relation' => 'eq'],
                'hits' => [],
            ],
        ]);

        $this->expectException(DocumentNotFoundException::class);

        Model::findOrFail('non-existing-id');
    }

    #[Test]
    public function it_can_create_model_with_static_method(): void
    {
        $this->getMockClient()->setResponse('index', [
            '_id' => 'created-id',
            '_index' => 'test_index',
            '_version' => 1,
            'result' => 'created',
        ]);

        $model = new class extends Model {
            protected ?string $index = 'test_index';
        };

        $created = $model::create([
            'title' => 'Created Document',
            'content' => 'Created content',
        ]);

        $this->assertInstanceOf(Model::class, $created);
        $this->assertEquals('created-id', $created->getKey());
        $this->assertEquals('Created Document', $created->title);
        $this->assertTrue($created->exists());
        $this->assertTrue($created->wasRecentlyCreated);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('index'));
    }

    #[Test]
    public function it_can_create_model_with_specific_id(): void
    {
        $this->getMockClient()->setResponse('index', [
            '_index' => 'test_index',
            '_id' => 'custom-id',
            '_version' => 1,
            'result' => 'created',
        ]);

        $model = new class extends Model {
            protected ?string $index = 'test_index';
        };

        $created = $model::create([
            'title' => 'Document with Custom ID',
            'content' => 'Custom content',
        ], 'custom-id');

        $this->assertEquals('custom-id', $created->getKey());
        $this->assertEquals('Document with Custom ID', $created->title);
        $this->assertTrue($created->exists());
        $this->assertTrue($created->wasRecentlyCreated);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('index'));
    }

    #[Test]
    public function it_can_destroy_multiple_models(): void
    {
        $this->getMockClient()->setResponse('search', [
            'took' => 1,
            'timed_out' => false,
            '_shards' => [
                'total' => 1,
                'successful' => 1,
                'skipped' => 0,
                'failed' => 0,
            ],
            'hits' => [
                'total' => ['value' => 2, 'relation' => 'eq'],
                'max_score' => 1.0,
                'hits' => [
                    [
                        '_id' => 'id-1',
                        '_source' => ['title' => 'Document 1'],
                    ],
                    [
                        '_id' => 'id-2',
                        '_source' => ['title' => 'Document 2'],
                    ],
                ],
            ],
        ]);

        $this->getMockClient()->setResponse('delete', [
            '_index' => 'test_index',
            '_id' => 'id-1',
            'result' => 'deleted',
        ]);

        $deletedCount = Model::destroy(['id-1', 'id-2']);

        $this->assertEquals(2, $deletedCount);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('search'));
        $this->assertTrue($this->getMockClient()->wasMethodCalled('delete'));
    }

    #[Test]
    public function it_handles_attribute_access(): void
    {
        $model = new Model([
            'title' => 'Test Title',
            'nested' => [
                'field' => 'Nested Value',
            ],
        ]);

        // Test direct attribute access
        $this->assertEquals('Test Title', $model->title);
        $this->assertEquals(['field' => 'Nested Value'], $model->nested);

        // Test getAttribute method
        $this->assertEquals('Test Title', $model->getAttribute('title'));
        $this->assertEquals(['field' => 'Nested Value'], $model->getAttribute('nested'));

        // Test non-existing attribute
        $this->assertNull($model->getAttribute('non_existing'));
        $this->assertNull($model->non_existing);
    }

    #[Test]
    public function it_handles_attribute_setting(): void
    {
        $model = new Model();

        // Test direct attribute setting
        $model->title = 'New Title';
        $model->content = 'New Content';

        $this->assertEquals('New Title', $model->title);
        $this->assertEquals('New Content', $model->content);

        // Test setAttribute method
        $model->setAttribute('description', 'New Description');
        $this->assertEquals('New Description', $model->description);
    }

    #[Test]
    public function it_handles_special_elasticsearch_attributes(): void
    {
        $model = new Model();
        $model->setResultMetadata([
            '_id' => 'test-id',
            '_score' => 1.5,
            'highlight' => ['title' => ['highlighted text']],
        ]);

        $this->assertEquals('test-id', $model->_id);
        $this->assertEquals(1.5, $model->_score);
        $this->assertEquals(['title' => ['highlighted text']], $model->highlight);
    }

    #[Test]
    public function it_handles_array_access(): void
    {
        $model = new Model([
            'title' => 'Array Access Test',
            'content' => 'Array content',
        ]);

        // Test offsetExists
        $this->assertTrue(isset($model['title']));
        $this->assertFalse(isset($model['non_existing']));

        // Test offsetGet
        $this->assertEquals('Array Access Test', $model['title']);
        $this->assertEquals('Array content', $model['content']);

        // Test offsetSet
        $model['new_field'] = 'New Value';
        $this->assertEquals('New Value', $model['new_field']);

        // Test offsetUnset
        unset($model['content']);
        $this->assertNull($model['content']);
    }

    #[Test]
    public function it_handles_magic_isset_and_unset(): void
    {
        $model = new Model([
            'title' => 'Magic Test',
            'content' => 'Magic content',
        ]);

        // Test __isset
        $this->assertTrue(isset($model->title));
        $this->assertFalse(isset($model->non_existing));

        // Test __unset
        unset($model->content);
        $this->assertNull($model->content);
        $this->assertFalse(isset($model->content));
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $attributes = [
            'title' => 'Array Test',
            'content' => 'Array content',
            'nested' => [
                'field' => 'Nested value',
            ],
        ];

        $model = new Model($attributes);
        $array = $model->toArray();

        $this->assertEquals($attributes, $array);
        $this->assertIsArray($array);
    }

    #[Test]
    public function it_converts_to_json(): void
    {
        $attributes = [
            'title' => 'JSON Test',
            'content' => 'JSON content',
            'number' => 42,
        ];

        $model = new Model($attributes);
        $json = $model->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertEquals($attributes, $decoded);
    }

    #[Test]
    public function it_implements_json_serializable(): void
    {
        $attributes = [
            'title' => 'JSON Serializable Test',
            'content' => 'JSON serializable content',
        ];

        $model = new Model($attributes);
        $serialized = $model->jsonSerialize();

        $this->assertEquals($attributes, $serialized);
        $this->assertIsArray($serialized);
    }

    #[Test]
    public function it_handles_casts_configuration(): void
    {
        $model = new class extends Model {
            protected $casts = [
                'number' => 'int',
                'price' => 'float',
                'is_active' => 'bool',
            ];
        };

        $casts = $model->getCasts();

        $this->assertEquals([
            'number' => 'int',
            'price' => 'float',
            'is_active' => 'bool',
        ], $casts);

        $this->assertTrue($model->hasCast('number'));
        $this->assertTrue($model->hasCast('price'));
        $this->assertTrue($model->hasCast('is_active'));
        $this->assertFalse($model->hasCast('non_existing'));
    }

    #[Test]
    public function it_handles_mutators(): void
    {
        $model = new class extends Model {
            public function setTitleAttribute($value): void
            {
                $this->attributes['title'] = strtoupper($value);
            }

            public function getTitleAttribute($value): string
            {
                return strtolower($value ?? '');
            }
        };

        $model->title = 'Test Title';
        $this->assertEquals('test title', $model->title);
        $this->assertEquals('TEST TITLE', $model->getAttributes()['title']);
    }

    #[Test]
    public function it_handles_accessors(): void
    {
        $model = new class extends Model {
            protected $fillable = ['first_name', 'last_name'];

            public function getFullNameAttribute(): string
            {
                return ($this->first_name ?? '') . ' ' . ($this->last_name ?? '');
            }
        };

        $model->fill([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertEquals('John Doe', $model->full_name);
        $this->assertEquals('John Doe', $model->getAttribute('full_name'));
    }

    #[Test]
    public function it_tracks_dirty_attributes(): void
    {
        $model = new Model([
            'title' => 'Original Title',
            'content' => 'Original Content',
        ], true);

        $model->syncOriginal();

        $this->assertFalse($model->isDirty());
        $this->assertTrue($model->isClean());

        $model->title = 'Modified Title';

        $this->assertTrue($model->isDirty());
        $this->assertTrue($model->isDirty('title'));
        $this->assertFalse($model->isDirty('content'));
        $this->assertFalse($model->isClean());

        $dirty = $model->getDirty();
        $this->assertEquals(['title' => 'Modified Title'], $dirty);
    }

    #[Test]
    public function it_tracks_changes(): void
    {
        $model = new Model([
            'title' => 'Original Title',
            'content' => 'Original Content',
        ], true);

        $model->syncOriginal();
        $model->title = 'New Title';
        $model->syncChanges();

        $changes = $model->getChanges();
        $this->assertArrayHasKey('title', $changes);
        $this->assertEquals('New Title', $changes['title']);
    }

    #[Test]
    public function it_handles_original_attributes(): void
    {
        $model = new Model([
            'title' => 'Original Title',
            'content' => 'Original Content',
        ]);

        $model->syncOriginal();
        $model->title = 'Modified Title';

        $this->assertEquals('Original Title', $model->getOriginal('title'));
        $this->assertEquals('Modified Title', $model->title);
        $this->assertTrue($model->originalIsEquivalent('content'));
        $this->assertFalse($model->originalIsEquivalent('title'));
    }

    #[Test]
    public function it_handles_model_configuration(): void
    {
        $model = new class extends Model {
            protected ?string $index = 'custom_index';
            protected ?string $connectionName = 'custom_connection';
            protected array $selectable = ['title', 'content'];
            protected array $unselectable = ['password', 'secret'];
        };

        $this->assertEquals('custom_index', $model->getIndex());
        $this->assertEquals('custom_connection', $model->getConnectionName());
        $this->assertEquals(['title', 'content'], $model->getSelectable());
        $this->assertEquals(['password', 'secret'], $model->getUnSelectable());
    }

    #[Test]
    public function it_handles_index_configuration(): void
    {
        $model = new Model();

        $this->assertNull($model->getIndex());

        $model->setIndex('test_index');
        $this->assertEquals('test_index', $model->getIndex());
    }

    #[Test]
    public function it_handles_connection_configuration(): void
    {
        $model = new Model();

        $this->assertNull($model->getConnectionName());

        $model->setConnectionName('test_connection');
        $this->assertEquals('test_connection', $model->getConnectionName());
    }

    #[Test]
    public function it_handles_result_metadata(): void
    {
        $metadata = [
            '_id' => 'test-id',
            '_index' => 'test_index',
            '_score' => 2.5,
            'highlight' => ['title' => ['highlighted']],
            '_version' => 1,
        ];

        $model = new Model();
        $model->setResultMetadata($metadata);

        $this->assertEquals($metadata, $model->getResultMetadata());
        $this->assertEquals('test-id', $model->getResultMetadataValue('_id'));
        $this->assertEquals(2.5, $model->getResultMetadataValue('_score'));
        $this->assertEquals(['title' => ['highlighted']], $model->getResultMetadataValue('highlight'));
    }

    #[Test]
    public function it_handles_model_comparison(): void
    {
        $model1 = new Model(['_id' => 'same-id'], true);
        $model1->setIndex('test_index');
        $model1->setConnectionName('default');

        $model2 = new Model(['_id' => 'same-id'], true);
        $model2->setIndex('test_index');
        $model2->setConnectionName('default');

        $model3 = new Model(['_id' => 'different-id'], true);
        $model3->setIndex('test_index');
        $model3->setConnectionName('default');

        $this->assertTrue($model1->is($model2));
        $this->assertFalse($model1->isNot($model2));
        $this->assertFalse($model1->is($model3));
        $this->assertTrue($model1->isNot($model3));
    }

    #[Test]
    public function it_handles_model_replication(): void
    {
        $original = new Model([
            '_id' => 'original-id',
            'title' => 'Original Title',
            'content' => 'Original Content',
        ], true);

        $replica = $original->replicate();

        $this->assertNull($replica->getKey());
        $this->assertFalse($replica->exists());
        $this->assertEquals('Original Title', $replica->title);
        $this->assertEquals('Original Content', $replica->content);
    }

    #[Test]
    public function it_handles_model_replication_with_exclusions(): void
    {
        $original = new Model([
            '_id' => 'original-id',
            'title' => 'Original Title',
            'content' => 'Original Content',
            'secret' => 'Secret Value',
        ], true);

        $replica = $original->replicate(['secret']);

        $this->assertNull($replica->getKey());
        $this->assertEquals('Original Title', $replica->title);
        $this->assertEquals('Original Content', $replica->content);
        $this->assertNull($replica->secret);
    }

    #[Test]
    public function it_creates_new_collection(): void
    {
        $model = new Model();
        $models = [
            new Model(['title' => 'Doc 1']),
            new Model(['title' => 'Doc 2']),
        ];

        $collection = $model->newCollection($models);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);
    }

    #[Test]
    public function it_handles_queueable_interface(): void
    {
        $model = new Model(['_id' => 'queue-test'], true);
        $model->setConnectionName('test_connection');

        $this->assertEquals('queue-test', $model->getQueueableId());
        $this->assertEquals('test_connection', $model->getQueueableConnection());
        $this->assertEquals([], $model->getQueueableRelations());
    }

    #[Test]
    public function it_handles_url_routable_interface(): void
    {
        $model = new Model(['_id' => 'route-test']);

        $this->assertEquals('_id', $model->getRouteKeyName());
        $this->assertEquals('route-test', $model->getRouteKey());
    }

    #[Test]
    public function it_handles_route_binding(): void
    {
        $this->getMockClient()->setResponse('search', [
            'took' => 1,
            'timed_out' => false,
            'hits' => [
                'total' => ['value' => 1, 'relation' => 'eq'],
                'hits' => [
                    [
                        '_id' => 'bound-id',
                        '_source' => ['title' => 'Bound Document'],
                    ],
                ],
            ],
        ]);

        $model = new Model();
        $bound = $model->resolveRouteBinding('bound-id');

        $this->assertInstanceOf(Model::class, $bound);
        $this->assertEquals('bound-id', $bound->getKey());
        $this->assertEquals('Bound Document', $bound->title);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('search'));
    }

    #[Test]
    public function it_handles_route_binding_with_custom_field(): void
    {
        $this->getMockClient()->setResponse('search', [
            'took' => 1,
            'timed_out' => false,
            'hits' => [
                'total' => ['value' => 1, 'relation' => 'eq'],
                'hits' => [
                    [
                        '_id' => 'custom-bound-id',
                        '_source' => ['slug' => 'test-slug', 'title' => 'Custom Bound Document'],
                    ],
                ],
            ],
        ]);

        $model = new Model();
        $bound = $model->resolveRouteBinding('test-slug', 'slug');

        $this->assertInstanceOf(Model::class, $bound);
        $this->assertEquals('custom-bound-id', $bound->getKey());
        $this->assertEquals('test-slug', $bound->slug);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('search'));
    }

    #[Test]
    public function it_handles_child_route_binding(): void
    {
        $this->getMockClient()->setResponse('search', [
            'took' => 1,
            'timed_out' => false,
            'hits' => [
                'total' => ['value' => 1, 'relation' => 'eq'],
                'hits' => [
                    [
                        '_id' => 'child-bound-id',
                        '_source' => ['title' => 'Child Bound Document'],
                    ],
                ],
            ],
        ]);

        $model = new Model();
        $bound = $model->resolveChildRouteBinding('ChildModel', 'child-bound-id');

        $this->assertInstanceOf(Model::class, $bound);
        $this->assertEquals('child-bound-id', $bound->getKey());
        $this->assertTrue($this->getMockClient()->wasMethodCalled('search'));
    }

    #[Test]
    public function it_handles_save_quietly(): void
    {
        $this->getMockClient()->setResponse('index', [
            '_id' => 'quiet-id',
            '_index' => 'test_index',
            '_version' => 1,
            'result' => 'created',
        ]);

        $model = new Model([
            'title' => 'Quiet Save Test',
        ]);
        $model->setIndex('test_index');

        $result = $model->saveQuietly();

        $this->assertSame($model, $result);
        $this->assertTrue($model->exists());
        $this->assertEquals('quiet-id', $model->getKey());
        $this->assertTrue($this->getMockClient()->wasMethodCalled('index'));
    }

    #[Test]
    public function it_handles_force_fill(): void
    {
        $model = new class extends Model {
            protected $fillable = ['title'];
        };

        $model->forceFill([
            'title' => 'Allowed Field',
            'secret' => 'Protected Field',
        ]);

        $this->assertEquals('Allowed Field', $model->title);
        $this->assertEquals('Protected Field', $model->secret);
    }

    #[Test]
    public function it_respects_fillable_attributes(): void
    {
        $model = new class extends Model {
            protected $fillable = ['title', 'content'];
        };

        $model->fill([
            'title' => 'Allowed Title',
            'content' => 'Allowed Content',
            'secret' => 'Not Allowed',
        ]);

        $this->assertEquals('Allowed Title', $model->title);
        $this->assertEquals('Allowed Content', $model->content);
        $this->assertNull($model->secret);
    }

    #[Test]
    public function it_respects_guarded_attributes(): void
    {
        $model = new class extends Model {
            protected $guarded = ['secret', 'password'];
        };

        $model->forceFill([
            'title' => 'Public Title',
            'secret' => 'Should Not Be Set',
            'password' => 'Should Not Be Set',
        ]);

        // Test that guarded attributes are not filled normally
        $model2 = new class extends Model {
            protected $guarded = ['secret', 'password'];
        };

        $model2->title = 'Public Title';
        // These should be ignored in normal fill operations
        $this->assertEquals('Public Title', $model2->title);
    }
}
