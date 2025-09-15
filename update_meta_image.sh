#!/bin/bash

# Define the meta tags to add
META_TAGS='  <meta property="og:image" content="/atex-latam-meta-image.png">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="/atex-latam-meta-image.png">'

# Find all HTML files and update them
find /Users/robinklaiss/Dev/atex-calculadora-losas/public -name "*.html" | while read -r file; do
  # Check if the file already has the meta image tag
  if ! grep -q 'og:image' "$file"; then
    # Insert the meta tags after the viewport meta tag
    sed -i '' "/<meta content=\"width=device-width, initial-scale=1\" name=\"viewport\">/a \\\n$META_TAGS" "$file"
    echo "Updated: $file"
  else
    echo "Skipped (already has meta image): $file"
  fi
done

echo "Meta image update complete!"
