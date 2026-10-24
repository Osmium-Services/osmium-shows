SELECT S.id, S.name, S.start_date, S.end_date, S.deleted_at, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar
FROM {TABLE} AS S
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'show' AND record_id = S.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE S.deleted_at IS NOT NULL
ORDER BY S.deleted_at DESC
