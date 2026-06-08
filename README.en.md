# Choir Music Library

WordPress plugin for a protected choir score library.

## Installation

1. Under "Code", select "Download ZIP".
2. Upload the plugin ZIP in WordPress.
3. Activate the "Choir Music Library" plugin in the WordPress admin area.
4. Create music pieces under "Choir Scores".

## Content

Each music piece can contain:

- song name via the post title
- composer
- lyricist
- voicing
- additional information
- singing information
- tags
- multiple score PDFs
- multiple audio samples
- optional pronunciation help as PDF or audio
- optional miscellaneous files

## Access

The plugin reads membership levels from WP Simple Membership / Simple Membership. Each music piece can be restricted to selected levels in the sidebar. If no level is selected, all logged-in Simple Membership members may view the piece.

File and audio links are served through a protected WordPress route and check access before delivery.

## Member Submissions

Under `Choir Scores > Settings`, choose which Simple Membership levels may submit music. Members of these levels can submit new music pieces as well as files or changes for existing music pieces.

All submissions are saved for review first. Administrators find them under `Choir Scores > Submissions` and can approve or reject them. A new music piece is published, or an existing music piece is updated, only after approval.

## Frontend

Overview page:

```text
[chor_noten_uebersicht]
```

Single music piece on any page:

```text
[chor_musikstueck id="123"]
```

Submission form for allowed members:

```text
[chor_musik_einreichen]
```

Collection overview:

```text
[chor_sammlungen]
```

Single collection on any page:

```text
[chor_sammlung id="123"]
```

Music pieces are also available through the custom post type archive and single views.

## Language

The plugin language can be switched between German and English under `Choir Scores > Settings`.

## Paid Downloads

Each music piece has a `Payment` box where downloads can be locked until payment. Add a product key that appears in the WP Simple Shopping Cart purchase data, such as the product name or SKU. You can also paste the matching WP Simple Shopping Cart purchase shortcode.

After successful payment, the plugin uses WP Simple Shopping Cart payment hooks to store the purchase by buyer email and unlock downloads for that email.

## PDF Watermarks

PDF downloads are watermarked by the plugin itself. The side watermark follows this pattern:

```text
Optional leading text · Site main URL · Name · Email · Date
```

Watermarked PDFs are generated on first download per user and file, then cached in the upload folder under `cml-watermarked`.

Under `Choir Scores > Settings`, you can choose whether all PDF scores or only paid PDF scores receive a watermark. The base text can be changed there. If it is left empty, the site main URL is used. An optional leading text can be added before it.
The base text can also be hidden completely.
