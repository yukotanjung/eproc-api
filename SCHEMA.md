# Database Schema

This document outlines the structure of the database schema for your project. It includes the tables, their fields, and relationships.

## Tables

### Users

| Field       | Type     | Description               |
|-------------|----------|---------------------------|
| id          | UUID     | Primary key               |
| name        | String   | User's name               |
| photo       | String   | URL to user's photo       |
| email       | String   | User's email address      |
| password    | String   | User's password hash      |
| remember_me | String   | Remember me token         |
| last_login  | Timestamp| Last login timestamp      |
| roles       | Relation | Many-to-many with roles   |
| timestamps  | Timestamps| Created at and updated at |
| softdeletes | SoftDeletes | Soft delete function  |

### Roles

| Field       | Type     | Description                |
|-------------|----------|----------------------------|
| id          | UUID     | Primary key                |
| name        | String   | Role name                  |
| permissions | Relation | Many-to-many with permissions |
| users       | Relation | Many-to-many with users    |
| timestamps  | Timestamps| Created at and updated at |
| softdeletes | SoftDeletes | Soft delete function  |

### Permissions

| Field       | Type     | Description               |
|-------------|----------|---------------------------|
| id          | UUID     | Primary key               |
| name        | String   | Permission name           |
| key         | String   | Permission key            |
| subject     | String   | Subject of the permission |
| action      | String   | Action of the permission  |
| roles       | Relation | Many-to-many with roles   |

### Unregister Devices

| Field       | Type     | Description               |
|-------------|----------|---------------------------|
| id          | UUID     | Primary key               |
| device_id   | Relation | Foreign key to devices    |
| info        | JSON     | Additional information    |
| latitude    | Double   | Latitude coordinate       |
| longitude   | Double   | Longitude coordinate      |
| width       | Number   | Width of the device       |
| height      | Number   | Height of the device      |
| active      | Boolean  | Active status             |
| blocked     | Boolean  | Blocked status            |
| timestamps  | Timestamps| Created at and updated at |
| softdeletes | SoftDeletes | Soft delete function  |

### Devices

| Field       | Type     | Description               |
|-------------|----------|---------------------------|
| id          | UUID     | Primary key               |
| serial      | String   | Serial number             |
| info        | JSON     | Additional information    |
| latitude    | Double   | Latitude coordinate       |
| longitude   | Double   | Longitude coordinate      |
| width       | Number   | Width of the device       |
| height      | Number   | Height of the device      |
| tags        | Relation | Many-to-many with tags    |
| timestamps  | Timestamps| Created at and updated at |
| softdeletes | SoftDeletes | Soft delete function  |

### Geo Locations

| Field       | Type     | Description               |
|-------------|----------|---------------------------|
| id          | UUID     | Primary key               |
| name        | String   | Location name             |
| shape       | Geometry | Geometrical shape         |
| center      | Point    | Center point              |
| radius      | Double   | Radius                    |
| data        | JSON     | Additional data           |
| tags        | Relation | Many-to-many with tags    |
| timestamps  | Timestamps| Created at and updated at |
| softdeletes | SoftDeletes | Soft delete function  |

### Contents

| Field       | Type     | Description               |
|-------------|----------|---------------------------|
| id          | UUID     | Primary key               |
| name        | String   | Content name              |
| mime        | String   | MIME type                 |
| path        | String   | File path                 |
| tags        | Relation | Many-to-many with tags    |
| timestamps  | Timestamps| Created at and updated at |
| softdeletes | SoftDeletes | Soft delete function  |

### Playlists

| Field       | Type     | Description               |
|-------------|----------|---------------------------|
| id          | UUID     | Primary key               |
| name        | String   | Playlist name             |
| tags        | Relation | Many-to-many with tags    |
| timestamps  | Timestamps| Created at and updated at |
| softdeletes | SoftDeletes | Soft delete function  |

### Playlist Contents

| Field        | Type     | Description               |
|--------------|----------|---------------------------|
| playlist_id  | Relation | Foreign key to playlists  |
| content_id   | Relation | Foreign key to contents   |
| duration     | Number   | Duration of content       |

### Campaigns

| Field       | Type     | Description               |
|-------------|----------|---------------------------|
| id          | UUID     | Primary key               |
| name        | String   | Campaign name             |
| description | Text     | Campaign description      |
| is_live     | Boolean  | Live status               |
| start_date  | Timestamp| Start date                |
| end_date    | Timestamp| End date                  |
| tags        | Relation | Many-to-many with tags    |
| data        | JSON     | Additional data           |
| softdeletes | SoftDeletes | Soft delete function  |

### Campaign Devices

| Field       | Type     | Description               |
|-------------|----------|---------------------------|
| id          | UUID     | Primary key               |
| campaign_id | Relation | Foreign key to campaigns  |
| device_id   | Relation | Foreign key to devices    |

### Campaign Playlists

| Field        | Type     | Description               |
|--------------|----------|---------------------------|
| id           | UUID     | Primary key               |
| name         | String   | Playlist name             |
| duplicate_of | Relation | Foreign key to playlists  |
| campaign_id  | Relation | Foreign key to campaigns  |

### Campaign Playlist Contents

| Field               | Type     | Description               |
|---------------------|----------|---------------------------|
| campaign_id         | Relation | Foreign key to campaigns  |
| campaign_playlist_id| Relation | Foreign key to campaign_playlists |
| content_id          | Relation | Foreign key to contents   |
| duration            | Number   | Duration of content       |

### Campaign Schedules

| Field               | Type     | Description               |
|---------------------|----------|---------------------------|
| campaign_id         | Relation | Foreign key to campaigns  |
| campaign_playlist_id| Relation | Foreign key to campaign_playlists |
| active              | Boolean  | Active status             |
| start_time          | Timestamp| Start time                |
| end_time            | Timestamp| End time                  |

### Tags

| Field       | Type     | Description               |
|-------------|----------|---------------------------|
| id          | UUID     | Primary key               |
| name        | String   | Tag name                  |
| data        | JSON     | Additional data           |
| softdeletes | SoftDeletes | Soft delete function  |

## Relationships

### Role-User Relationship

- `_users.roles` is a many-to-many relationship with `_roles` via `_role_user`.

### Role-Permission Relationship

- `_roles.permissions` is a many-to-many relationship with `_permissions` via `_role_permission`.

### Device-Tag Relationship

- `_devices.tags` is a many-to-many relationship with `_tags` via `_tag_device`.

### Geo Location-Tag Relationship

- `_geo_locations.tags` is a many-to-many relationship with `_tags` via `_tag_geo_location`.

### Content-Tag Relationship

- `_contents.tags` is a many-to-many relationship with `_tags` via `_tag_content`.

### Playlist-Tag Relationship

- `_playlists.tags` is a many-to-many relationship with `_tags` via `_tag_playlist`.

### Campaign-Tag Relationship

- `_campaigns.tags` is a many-to-many relationship with `_tags` via `_tag_campaign`.

### Campaign-Device Relationship

- `_campaign_devices` links `_campaigns` and `_devices`.

### Campaign-Playlist Relationship

- `_campaign_playlists` links `_campaigns` and `_playlists`.

### Campaign Playlist-Content Relationship

- `_campaign_playlist_contents` links `_campaign_playlists` and `_contents`.

### Campaign-Schedule Relationship

- `_campaign_schedules` links `_campaigns` and `_campaign_playlists`.

## Timestamps and Soft Deletes

- All tables include `timestamps` (created at and updated at fields).
- All tables include `softdeletes` (soft delete timestamp field).

## Notes

- UUID is used as the primary key for all tables to ensure uniqueness.
- JSON fields are used for storing additional information.
- Geometry and Point fields are used for storing geographical data.
- Relations are indicated to show the connections between tables.

This schema is designed to support a flexible and scalable system for managing users, roles, permissions, devices, geo locations, contents, playlists, campaigns, and tags.