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
    s.image_filename,
    s.is_published
FROM {TABLE} s
WHERE s.deleted_at IS NULL
ORDER BY s.start_date DESC
