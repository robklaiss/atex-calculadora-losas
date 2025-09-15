#!/usr/bin/env python3
import os
import re

# Directory containing HTML files
public_dir = '/Users/robinklaiss/Dev/atex-calculadora-losas/public'

# Meta tags to add
meta_tags = '''  <meta property="og:image" content="/atex-latam-meta-image.png">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="/atex-latam-meta-image.png">'''

# Find all HTML files
for root, _, files in os.walk(public_dir):
    for file in files:
        if file.endswith('.html'):
            filepath = os.path.join(root, file)
            try:
                # Read the file
                with open(filepath, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                # Skip if already has og:image
                if 'og:image' in content:
                    print(f"Skipped (already has meta image): {filepath}")
                    continue
                
                # Insert meta tags after viewport meta tag
                pattern = r'(<meta[^>]*name=["\']viewport["\'][^>]*>)'
                replacement = f'\\1\n{meta_tags}'
                new_content = re.sub(pattern, replacement, content, count=1, flags=re.IGNORECASE)
                
                # Write the updated content back to the file
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                
                print(f"Updated: {filepath}")
                
            except Exception as e:
                print(f"Error processing {filepath}: {str(e)}")

print("Meta image update complete!")
