<?php
/**
 * Course Registry
 *
 * A registry to manage COURSE_Model instances with caching.
 *
 * @package sieben
 */
class COURSE_Registry
{
    private static array $courses = [];

    public static function get(int $post_id): COURSE_Model
    {
        // 1️⃣ Prüfen, ob schon im Runtime Cache
        if (isset(self::$courses[$post_id])) {
            return self::$courses[$post_id];
        }

        // 2️⃣ Prüfen, ob im WP Object Cache
        $cache_key = 'course_model_' . $post_id;
        $cached = wp_cache_get($cache_key, 'course_models');
        if ($cached instanceof COURSE_Model) {
            self::$courses[$post_id] = $cached;
            return $cached;
        }

        // 3️⃣ Wenn nicht vorhanden, neu laden
        $course = new COURSE_Model($post_id);

        // 4️⃣ In WP Object Cache speichern (10 Minuten TTL)
        wp_cache_set($cache_key, $course, 'course_models', 600);

        // 5️⃣ In Runtime Cache speichern
        self::$courses[$post_id] = $course;

        return $course;
    }
}

