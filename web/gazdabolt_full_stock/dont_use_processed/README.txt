Photo inventory: first three photos ordered by EXIF DateTimeOriginal, ascending.
All 170 source JPG files had EXIF capture dates.

Source photo                              EXIF capture time
processed_20260915_101038.jpg              2026-09-15 10:10:40
processed_20260915_101047.jpg              2026-09-15 10:10:47
processed_20260915_101051.jpg              2026-09-15 10:10:52

The three photos remain in the parent gazdabolt_full_stock directory, renamed
with the processed_ prefix. Image contents and metadata are unchanged.

products_first_3.csv contains the requested three columns:
unique id, product name, product type.
The ID prefix is the original photo filename stem; the suffix identifies a
product observation within that photo. IDs are observation IDs, not SKU IDs.

Each row describes a visually distinguishable product or variant, not every
physical copy. Repeated coils, handles and identical tools are grouped within
a photo. Products appearing in more than one photo are recorded separately,
so rows must not be interpreted as a deduplicated catalogue or stock counts.

Names are visual descriptions where exact commercial names are unreadable.
Brands, dimensions and models are not inferred from appearance. Uncertain
identifications are marked in the CSV. Similar hoses may have different
unreadable diameters; those variants cannot be reliably separated here.
Floor-runner names describe visible surfaces; exact material is not confirmed.
Background items too obscured to identify reliably are omitted.
