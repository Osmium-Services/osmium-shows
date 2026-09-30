UPDATE {TABLE}
SET name = :name,
    location = :location,
    postcode = :postcode,
    latitude = :latitude,
    longitude = :longitude,
    start_date = :start_date,
    end_date = :end_date,
    link_url = :link_url,
    is_published = :is_published
WHERE id = :id
