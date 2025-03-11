<?php

/** @noinspection PhpUndefinedFieldInspection */

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests;

use ArrayAccess;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Matchory\Elasticsearch\ElasticsearchServiceProvider;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Tests\Traits\ResolvesConnections;
use Orchestra\Testbench\TestCase;

class ModelTest extends TestCase
{
    use ResolvesConnections;

    public function testAll(): void {}

    public function testAppend(): void {}

    public function testAttributesToArray(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);

        self::assertSame(
            $model->toArray(),
            $model->attributesToArray(),
        );
    }

    public function testCacheMutatedAttributes(): void {}

    public function testDelete(): void {}

    public function testEncryptUsing(): void {}

    public function testExists(): void {}

    public function testExistsCanBePassedOnConstruction(): void
    {
        $model = new Model([], true);
        self::assertTrue($model->exists());
    }

    public function testExistsIsFalseByDefault(): void
    {
        $model = new Model();
        self::assertFalse($model->exists());
    }

    public function testFillJsonAttribute(): void {}

    public function testFind(): void {}

    public function testFromDateTime(): void {}

    public function testFromEncryptedString(): void {}

    public function testGetAttribute(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);

        self::assertSame(
            $model->getAttribute('foo'),
            $model->foo,
        );

        self::assertSame(
            $model->getAttribute('foo'),
            'bar',
        );
    }

    public function testGetAttributeReturnsNullForMissingAttributes(): void
    {
        $model = new Model();

        self::assertNull($model->getAttribute('foo'));
    }

    public function testGetAttributeValue(): void {}

    public function testGetAttributes(): void {}

    public function testGetCasts(): void {}

    public function testGetChanges(): void {}

    public function testGetDateFormat(): void {}

    public function testGetDates(): void {}

    public function testGetDirty(): void {}

    public function testGetHighlights(): void {}

    public function testGetId(): void
    {
        $model = (new Model())->newInstance([], ['_id' => '42'], true);

        self::assertSame('42', $model->getId());
    }

    public function testGetIdReturnsNullIfModelDoesNotExist(): void
    {
        $model = (new Model())->newInstance([], [], false);

        self::assertNull($model->getId());
    }

    public function testGetKey(): void {}

    public function testGetMutatedAttributes(): void {}

    public function testGetOriginal(): void
    {
        $model = (new Model())->newInstance(
            ['foo' => 'bar'],
            ['_id' => '42'],
            true,
        );

        self::assertSame('bar', $model->foo);

        $model->foo = 'quz';

        self::assertNotSame('bar', $model->foo);
        self::assertSame('bar', $model->getOriginal('foo'));
    }

    public function testGetQueueableConnectionReturnsConnectionName(): void
    {
        $model = new Model();

        self::assertSame(
            $model->getConnectionName(),
            $model->getQueueableConnection(),
        );
    }

    public function testGetQueueableId(): void
    {
        $model = new Model([
            '_id' => 'foo',
        ]);

        self::assertSame('foo', $model->getQueueableId());
    }

    public function testGetQueueableRelations(): void
    {
        self::assertEmpty((new Model())->getQueueableRelations());
    }

    public function testGetRawOriginal(): void {}

    public function testGetRelationValue(): void {}

    public function testGetRouteKeyName(): void
    {
        $model = new Model();

        self::assertSame('_id', $model->getRouteKeyName());
    }

    public function testGetRouteKeyReturnsId(): void
    {
        $model = new Model(['_id' => '42']);

        self::assertSame('42', $model->getRouteKey());
    }

    public function testGetRouteKeyReturnsValueFromConfiguredField(): void
    {
        $model = new class extends Model {
            public function getRouteKeyName(): string
            {
                return 'foo';
            }
        };

        $instance = $model->newInstance(
            ['foo' => '42'],
            ['_id' => '24'],
        );

        self::assertSame('42', $instance->getRouteKey());
    }

    public function testGetSelectable(): void {}

    public function testGetUnSelectable(): void {}

    public function testHasCast(): void
    {
        $model = new class extends Model {
            protected $casts = [
                'foo' => 'int',
            ];
        };

        self::assertTrue($model->hasCast('foo'));
        self::assertFalse($model->hasCast('bar'));
    }

    public function testHasGetMutator(): void {}

    public function testHasSetMutator(): void {}

    public function testImplementsAllRequiredInterfaces(): void
    {
        $model = new Model();

        self::assertInstanceOf(Arrayable::class, $model);
        self::assertInstanceOf(ArrayAccess::class, $model);
        self::assertInstanceOf(Jsonable::class, $model);
        self::assertInstanceOf(JsonSerializable::class, $model);
        self::assertInstanceOf(UrlRoutable::class, $model);
        self::assertInstanceOf(QueueableEntity::class, $model);
    }

    public function testIndexIsSetOnQuery(): void
    {
        $model = new Model();
        $model->setIndex('foo');
        $query = $model->newQuery();

        self::assertSame('foo', $model->getIndex());
        self::assertSame('foo', $query->getIndex());
    }

    public function testIsClean(): void
    {
        $model = (new Model())->newInstance([
            'foo' => 42,
            'bar' => 'baz',
        ]);

        self::assertTrue($model->isClean());
        self::assertTrue($model->isClean(['foo']));
        self::assertTrue($model->isClean(['bar']));
        self::assertTrue($model->isClean(['foo', 'bar']));

        $model->foo = 43;

        self::assertFalse($model->isClean());
        self::assertFalse($model->isClean(['foo']));
        self::assertTrue($model->isClean(['bar']));
        self::assertFalse($model->isClean(['foo', 'bar']));
    }

    public function testIsDirty(): void
    {
        $model = (new Model())->newInstance([
            'foo' => 'bar',
        ], ['_id' => '42'], true);

        self::assertFalse($model->isDirty());
        self::assertFalse($model->isDirty('foo'));

        $model->foo = 'quz';

        self::assertTrue($model->isDirty());
        self::assertTrue($model->isDirty('foo'));
    }

    public function testJsonSerialize(): void {}

    public function testMergeCasts(): void
    {
        $model = new class extends Model {
            protected $casts = [
                'foo' => 'int',
            ];
        };

        self::assertTrue($model->hasCast('foo'));
        self::assertFalse($model->hasCast('bar'));

        $model->mergeCasts([
            'bar' => 'int',
        ]);

        self::assertTrue($model->hasCast('foo'));
        self::assertTrue($model->hasCast('bar'));
    }

    public function testModelIndex(): void
    {
        $model = new Model();
        $model->setIndex('foo');

        self::assertSame('foo', $model->getIndex());
    }

    public function testModelIndexDefaultsToNull(): void
    {
        $model = new Model();

        self::assertNull($model->getIndex());
    }

    public function testModelIndexFromUserlandProp(): void
    {
        $model = new class extends Model {
            protected ?string $index = 'bar';
        };

        self::assertSame('bar', $model->getIndex());

        $model->setIndex('foo');

        self::assertSame('foo', $model->getIndex());
    }

    public function testOffsetExists(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);

        self::assertTrue(isset($model->foo));
        self::assertFalse(isset($model->bar));
    }

    public function testOffsetGet(): void {}

    public function testOffsetSet(): void {}

    public function testOffsetUnset(): void
    {
        $instance = (new Model())->newInstance([
            'foo' => 'bar',
        ]);

        self::assertEquals('bar', $instance->foo);

        unset($instance->foo);

        self::assertNull($instance->foo);
    }

    public function testOnly(): void {}

    public function testOriginalIsEquivalent(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);

        self::assertFalse($model->originalIsEquivalent('foo'));

        $model->syncOriginal();

        self::assertTrue($model->originalIsEquivalent('foo'));

        $model->foo = 'baz';

        self::assertFalse($model->originalIsEquivalent('foo'));
    }

    public function testRelationsToArray(): void {}

    public function testResolveChildRouteBinding(): void
    {
        $this
            ->mockClient()
            ->expects(self::any())
            ->method('search')
            ->with([
                'from' => 0,
                'size' => 1,
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                [
                                    'term' => [
                                        '_id' => 42,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->willReturn([
                'hits' => [
                    'hits' => [
                        ['_id' => '42'],
                    ],
                ],
            ]);

        $model = (new Model())->newInstance([
            'foo' => 42,
            'bar' => 'baz',
        ]);

        self::assertEquals(
            $model->resolveRouteBinding(42),
            $model->resolveChildRouteBinding('', 42),
        );
    }

    public function testResolveRouteBinding(): void
    {
        $this
            ->mockClient()
            ->expects(self::any())
            ->method('search')
            ->with([
                'from' => 0,
                'size' => 1,
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                [
                                    'term' => [
                                        '_id' => 42,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->willReturn([
                'hits' => [
                    'hits' => [
                        ['_id' => '42'],
                    ],
                ],
            ]);

        $model = (new Model())->resolveRouteBinding(42);

        self::assertSame($model->_id, '42');
    }

    public function testResolveRouteBindingFromField(): void
    {
        $this
            ->mockClient()
            ->expects(self::any())
            ->method('search')
            ->with([
                'from' => 0,
                'size' => 1,
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                [
                                    'term' => [
                                        'foo' => 42,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->willReturn([
                'hits' => [
                    'hits' => [
                        ['_id' => '42'],
                    ],
                ],
            ]);

        $model = (new Model())->resolveRouteBinding(42, 'foo');

        self::assertSame($model->_id, '42');
    }

    public function testSaveCreatesNewModel(): void
    {
        $this
            ->mockClient()
            ->expects(self::any())
            ->method('index')
            ->willReturn((object) [
                '_id' => '42',
            ]);

        $model = new Model();
        $model->save();

        self::assertSame('42', $model->getId());
    }

    public function testSaveUpdatesExistingModel(): void
    {
        $this
            ->mockClient()
            ->expects(self::any())
            ->method('update')
            ->willReturn((object) [
                '_id' => '42',
                'foo' => 'bar',
            ]);

        $model = (new Model())->newInstance(
            ['foo' => 'bar'],
            ['_id' => '42'],
            true,
        );
        $model->save();

        self::assertSame('42', $model->getId());
    }

    public function testSetAppends(): void {}

    public function testSetAttribute(): void {}

    public function testSetConnection(): void {}

    public function testSetDateFormat(): void {}

    public function testSetRawAttributes(): void {}

    public function testSetType(): void {}

    public function testSyncChanges(): void
    {
        $model = (new Model())->newInstance([
            'foo' => 42,
            'bar' => 'baz',
        ]);

        $model->syncChanges();
        self::assertEmpty($model->getChanges());

        $model->foo = 43;
        self::assertEmpty($model->getChanges());
        $model->syncChanges();
        self::assertNotEmpty($model->getChanges());
    }

    public function testSyncOriginal(): void {}

    public function testSyncOriginalAttribute(): void {}

    public function testSyncOriginalAttributes(): void {}

    public function testToArray(): void {}

    public function testToJson(): void {}

    public function testWasChanged(): void {}

    public function test__call(): void {}

    public function test__get(): void {}

    public function test__isset(): void {}

    public function test__set(): void {}

    public function test__unset(): void {}

    protected function getEnvironmentSetUp($app): void
    {
        $resolver = $this->createConnectionResolver();
        $app->instance(ConnectionResolverInterface::class, $resolver);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ElasticsearchServiceProvider::class,
        ];
    }
}
