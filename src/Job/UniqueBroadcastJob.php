<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Job;

use Crustum\Broadcasting\Event\UniqueFor;
use Exception;
use ReflectionClass;

/**
 * Unique Broadcast Job
 *
 * CakePHP Queue unique job for broadcast events. Relies on
 * `QueueManager` + `uniqueCache` config (`Job::$shouldBeUnique`).
 *
 * The per-event lifetime resolved by `resolveUniqueFor()` is carried in the
 * job data for forward compatibility; the effective deduplication window is
 * governed by the queue connection `uniqueCache` duration, as CakePHP Queue
 * offers no per-job lock TTL.
 *
 * @package Crustum\Broadcasting\Job
 * @see \Cake\Queue\QueueManager::push()
 */
class UniqueBroadcastJob extends BroadcastJob
{
    /**
     * Whether only one instance of this job (by data hash) may be queued.
     *
     * @var bool
     */
    public static bool $shouldBeUnique = true;

    /**
     * Resolve the uniqueness lifetime (in seconds) for a broadcast event.
     *
     * Precedence: `uniqueFor()` method, then `UniqueFor` attribute (including
     * inherited ones, unless a child class declares its own `$uniqueFor`
     * property), then the `$uniqueFor` property itself.
     *
     * @param object $event Broadcast event object
     * @return int|null Lifetime in seconds, null when the event declares none
     */
    public static function resolveUniqueFor(object $event): ?int
    {
        if (method_exists($event, 'uniqueFor')) {
            return (int)$event->uniqueFor();
        }

        $reflection = new ReflectionClass($event);

        $attribute = null;
        $attributeDeclaringClass = null;
        $class = $reflection;
        while ($class instanceof ReflectionClass) {
            $attributes = $class->getAttributes(UniqueFor::class);
            if ($attributes !== []) {
                $attribute = $attributes[0]->newInstance();
                $attributeDeclaringClass = $class;
                break;
            }

            foreach ($class->getTraits() as $trait) {
                $attributes = $trait->getAttributes(UniqueFor::class);
                if ($attributes !== []) {
                    $attribute = $attributes[0]->newInstance();
                    $attributeDeclaringClass = $class;
                    break 2;
                }
            }

            $class = $class->getParentClass();
        }

        if ($attribute instanceof UniqueFor && $attributeDeclaringClass instanceof ReflectionClass) {
            if (static::childPropertyOverridesAttribute($event, $reflection, $attributeDeclaringClass)) {
                $property = $reflection->getProperty('uniqueFor');

                return (int)$property->getValue($event);
            }

            return $attribute->seconds;
        }

        if ($reflection->hasProperty('uniqueFor')) {
            $property = $reflection->getProperty('uniqueFor');
            if ($property->isPublic() && $property->isInitialized($event)) {
                $uniqueFor = $property->getValue($event);
                if ($uniqueFor !== null) {
                    return (int)$uniqueFor;
                }
            }
        }

        return null;
    }

    /**
     * Whether a `$uniqueFor` property declared on a child class overrides an
     * inherited `UniqueFor` attribute.
     *
     * @param object $event Broadcast event object
     * @param \ReflectionClass<object> $reflection Event reflection
     * @param \ReflectionClass<object> $attributeDeclaringClass Class declaring the attribute
     * @return bool
     */
    protected static function childPropertyOverridesAttribute(
        object $event,
        ReflectionClass $reflection,
        ReflectionClass $attributeDeclaringClass,
    ): bool {
        if (!$reflection->hasProperty('uniqueFor')) {
            return false;
        }

        try {
            $property = $reflection->getProperty('uniqueFor');
        } catch (Exception) {
            return false;
        }

        return $property->isPublic()
            && $property->isInitialized($event)
            && $property->getValue($event) !== null
            && $property->getDeclaringClass()->isSubclassOf($attributeDeclaringClass->getName());
    }
}
