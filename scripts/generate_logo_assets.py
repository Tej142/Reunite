import os
from PIL import Image, ImageDraw

def create_logo_svg(path, size=512):
    # Radius is 26% of size
    rx = size * (22.0 / 84.0)
    # Scale for 24x24 viewBox: 38/84 of size
    scale = (size * (38.0 / 84.0)) / 20.0
    tx = (size - 24.0 * scale) / 2.0
    ty = (size - 24.0 * scale) / 2.0
    
    svg_content = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {size} {size}" width="{size}" height="{size}">
  <rect width="{size}" height="{size}" rx="{rx:.1f}" ry="{rx:.1f}" fill="#C1592F" />
  <g transform="translate({tx:.2f}, {ty:.2f}) scale({scale:.4f})">
    <path d="M12 2L14 10L22 12L14 14L12 22L10 14L2 12L10 10L12 2Z" fill="#F5F1E7" />
  </g>
</svg>
'''
    with open(path, "w", encoding="utf-8") as f:
        f.write(svg_content)
    print(f"Created SVG: {path}")

def create_logo_png(path, size=512):
    # 4x supersampling for high-quality smooth curves and anti-aliasing
    scale_factor = 4
    s = size * scale_factor
    rx = int(s * (22.0 / 84.0))
    
    # 1. Base image with transparency
    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    
    # 2. Draw terracotta squircle (#C1592F)
    terracotta = (193, 89, 47, 255)
    draw.rounded_rectangle([0, 0, s - 1, s - 1], radius=rx, fill=terracotta)
    
    # 3. Draw 4-point star in warm cream (#F5F1E7)
    cream = (245, 241, 231, 255)
    
    # Star geometry relative to s:
    # viewBox is 24x24, star tips at 2 and 22 (span 20 units)
    star_scale = (s * (38.0 / 84.0)) / 20.0
    tx = (s - 24.0 * star_scale) / 2.0
    ty = (s - 24.0 * star_scale) / 2.0
    
    # Path: M12 2 L14 10 L22 12 L14 14 L12 22 L10 14 L2 12 L10 10 Z
    points = [
        (tx + 12 * star_scale, ty + 2 * star_scale),   # Top tip
        (tx + 14 * star_scale, ty + 10 * star_scale),  # Inner top-right
        (tx + 22 * star_scale, ty + 12 * star_scale),  # Right tip
        (tx + 14 * star_scale, ty + 14 * star_scale),  # Inner bottom-right
        (tx + 12 * star_scale, ty + 22 * star_scale),  # Bottom tip
        (tx + 10 * star_scale, ty + 14 * star_scale),  # Inner bottom-left
        (tx + 2 * star_scale,  ty + 12 * star_scale),  # Left tip
        (tx + 10 * star_scale, ty + 10 * star_scale),  # Inner top-left
    ]
    
    draw.polygon(points, fill=cream)
    
    # Downsample using LANCZOS for crisp edges
    resampled = img.resize((size, size), Image.Resampling.LANCZOS)
    resampled.save(path, "PNG")
    print(f"Created PNG ({size}x{size}): {path}")
    return resampled

def main():
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    target_dir = os.path.join(base_dir, "Frontend", "img")
    os.makedirs(target_dir, exist_ok=True)
    
    # Create SVG
    svg_path = os.path.join(target_dir, "logo.svg")
    create_logo_svg(svg_path, 512)
    
    # Create PNGs (512, 192, 64, 32)
    png_512 = os.path.join(target_dir, "logo.png")
    create_logo_png(png_512, 512)
    
    png_192 = os.path.join(target_dir, "logo-192.png")
    create_logo_png(png_192, 192)
    
    png_64 = os.path.join(target_dir, "favicon.png")
    create_logo_png(png_64, 64)
    
    # Also create ICO
    ico_path = os.path.join(target_dir, "favicon.ico")
    img_512 = Image.open(png_512)
    img_512.save(ico_path, format="ICO", sizes=[(16, 16), (32, 32), (48, 48), (64, 64)])
    print(f"Created ICO: {ico_path}")

if __name__ == "__main__":
    main()
