SELECT
    s.id,
    s.name,
    s.location,
    s.postcode,
    s.latitude,
    s.longitude,
    s.start_date,
    s.end_date,
    s.link_url,
    s.image_filename
FROM {TABLE} s
WHERE s.deleted_at IS NULL
  AND s.is_published = 1
  AND s.end_date >= :today
ORDER BY s.start_date ASC
