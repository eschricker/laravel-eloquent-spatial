<?php

declare(strict_types=1);

namespace MatanYadaev\EloquentSpatial\Objects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use MatanYadaev\EloquentSpatial\Factory;
use MatanYadaev\EloquentSpatial\GeometryCast;

/**
 * @implements Arrayable<string, string|array>
 */
abstract class Geometry implements Castable, Arrayable, Jsonable, JsonSerializable
{
    abstract public function toWkt(): Expression;

    /**
     * @param int $options
     * 
     * @return string
     * 
     * @throws JsonException 
     */
    public function toJson($options = 0): string
    {
        $json = json_encode($this, $options);

        if(false === $json) {
            throw new JsonException('json_encode failed');
        }

        return $json;
    }

    /**
     * @param  string  $wkb
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public static function fromWkb(string $wkb): static
    {
        $geometry = Factory::parse($wkb, true);

        if (! ($geometry instanceof static)) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, %s given.', static::class, $geometry::class)
            );
        }

        return $geometry;
    }

    /**
     * @param  string  $geoJson
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public static function fromJson(string $geoJson): static
    {
        $geometry = Factory::parse($geoJson, false);

        if (! ($geometry instanceof static)) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, %s given.', static::class, $geometry::class)
            );
        }

        return $geometry;
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{type: string, coordinates: array<mixed>}
     */
    public function toArray(): array
    {
        return [
            'type' => class_basename(static::class),
            'coordinates' => $this->getCoordinates(),
        ];
    }

    /**
     * @return string
     * 
     * @throws JsonException
     */
    public function toFeatureCollectionJson(): string
    {
        if (static::class === GeometryCollection::class) {
            /**
             * @template TGeometry of Geometry
             * @var GeometryCollection<TGeometry> $this 
             * */
            $geometries = $this->geometries;
        } else {
            $geometries = collect([$this]);
        }

        $features = $geometries->map(static function (self $geometry): array {
            return [
                'type' => 'Feature',
                'properties' => [],
                'geometry' => $geometry->toArray(),
            ];
        });

        $json = json_encode([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);

        if(false === $json) {
            throw new JsonException('json encode failed');
        }

        return $json;
    }

    /**
     * @return array<mixed>
     */
    abstract public function getCoordinates(): array;

    /**
     * @param  array<string>  $arguments
     * @return CastsAttributes
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new GeometryCast(static::class);
    }
}
