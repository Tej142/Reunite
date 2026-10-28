import os
from PIL import Image, ImageDraw

def render_squircle_logo(size, scale_factor=4):
    s = size * scale_factor
    rx = int(s * (22.0 / 84.0))
    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    
    terracotta = (193, 89, 47, 255)
    draw.rounded_rectangle([0, 0, s - 1, s - 1], radius=rx, fill=terracotta)
    
    cream = (245, 241, 231, 255)
    star_scale = (s * (38.0 / 84.0)) / 20.0
    tx = (s - 24.0 * star_scale) / 2.0
    ty = (s - 24.0 * star_scale) / 2.0
    
    points = [
        (tx + 12 * star_scale, ty + 2 * star_scale),
        (tx + 14 * star_scale, ty + 10 * star_scale),
        (tx + 22 * star_scale, ty + 12 * star_scale),
        (tx + 14 * star_scale, ty + 14 * star_scale),
        (tx + 12 * star_scale, ty + 22 * star_scale),
        (tx + 10 * star_scale, ty + 14 * star_scale),
        (tx + 2 * star_scale,  ty + 12 * star_scale),
        (tx + 10 * star_scale, ty + 10 * star_scale),
    ]
    draw.polygon(points, fill=cream)
    return img.resize((size, size), Image.Resampling.LANCZOS)

def render_round_logo(size, scale_factor=4):
    s = size * scale_factor
    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    
    terracotta = (193, 89, 47, 255)
    draw.ellipse([0, 0, s - 1, s - 1], fill=terracotta)
    
    cream = (245, 241, 231, 255)
    star_scale = (s * (44.0 / 84.0)) / 20.0
    tx = (s - 24.0 * star_scale) / 2.0
    ty = (s - 24.0 * star_scale) / 2.0
    
    points = [
        (tx + 12 * star_scale, ty + 2 * star_scale),
        (tx + 14 * star_scale, ty + 10 * star_scale),
        (tx + 22 * star_scale, ty + 12 * star_scale),
        (tx + 14 * star_scale, ty + 14 * star_scale),
        (tx + 12 * star_scale, ty + 22 * star_scale),
        (tx + 10 * star_scale, ty + 14 * star_scale),
        (tx + 2 * star_scale,  ty + 12 * star_scale),
        (tx + 10 * star_scale, ty + 10 * star_scale),
    ]
    draw.polygon(points, fill=cream)
    return img.resize((size, size), Image.Resampling.LANCZOS)

def render_adaptive_foreground(canvas_size):
    # Safe zone for Android adaptive icons is 66-72dp out of 108dp (approx 62%)
    logo_size = int(canvas_size * 0.62)
    logo_img = render_squircle_logo(logo_size)
    
    canvas = Image.new("RGBA", (canvas_size, canvas_size), (0, 0, 0, 0))
    offset = (canvas_size - logo_size) // 2
    canvas.paste(logo_img, (offset, offset), logo_img)
    return canvas

def render_splash_screen(width, height):
    # Warm cream background: #F5EFE6
    bg_color = (245, 239, 230, 255)
    splash = Image.new("RGBA", (width, height), bg_color)
    
    # Emblem size approx 28% of min dimension
    min_dim = min(width, height)
    logo_size = max(64, min(256, int(min_dim * 0.28)))
    logo_img = render_squircle_logo(logo_size)
    
    pos_x = (width - logo_size) // 2
    pos_y = (height - logo_size) // 2
    splash.paste(logo_img, (pos_x, pos_y), logo_img)
    return splash

def update_android_assets(res_dir):
    print("Updating Android launcher mipmaps and splash screens in:", res_dir)
    
    densities = [
        ("mdpi", 48, 108),
        ("hdpi", 72, 162),
        ("xhdpi", 96, 216),
        ("xxhdpi", 144, 324),
        ("xxxhdpi", 192, 432)
    ]
    
    for density, icon_sz, fg_sz in densities:
        mdir = os.path.join(res_dir, f"mipmap-{density}")
        os.makedirs(mdir, exist_ok=True)
        
        # 1. ic_launcher.png (legacy squircle)
        launcher = render_squircle_logo(icon_sz)
        launcher.save(os.path.join(mdir, "ic_launcher.png"), "PNG")
        
        # 2. ic_launcher_round.png
        launcher_round = render_round_logo(icon_sz)
        launcher_round.save(os.path.join(mdir, "ic_launcher_round.png"), "PNG")
        
        # 3. ic_launcher_foreground.png (adaptive foreground)
        fg = render_adaptive_foreground(fg_sz)
        fg.save(os.path.join(mdir, "ic_launcher_foreground.png"), "PNG")
        
        print(f"  [mipmap-{density}] Updated launcher ({icon_sz}x{icon_sz}), round, and foreground ({fg_sz}x{fg_sz})")
        
    # Splash screens
    splash_port = [
        ("drawable-port-mdpi", 320, 480),
        ("drawable-port-hdpi", 480, 800),
        ("drawable-port-xhdpi", 720, 1280),
        ("drawable-port-xxhdpi", 960, 1600),
        ("drawable-port-xxxhdpi", 1280, 1920)
    ]
    for dname, w, h in splash_port:
        ddir = os.path.join(res_dir, dname)
        os.makedirs(ddir, exist_ok=True)
        sp = render_splash_screen(w, h)
        sp.save(os.path.join(ddir, "splash.png"), "PNG")
        print(f"  [{dname}] Updated splash ({w}x{h})")
        
    splash_land = [
        ("drawable-land-mdpi", 480, 320),
        ("drawable-land-hdpi", 800, 480),
        ("drawable-land-xhdpi", 1280, 720),
        ("drawable-land-xxhdpi", 1600, 960),
        ("drawable-land-xxxhdpi", 1920, 1280)
    ]
    for dname, w, h in splash_land:
        ddir = os.path.join(res_dir, dname)
        os.makedirs(ddir, exist_ok=True)
        sp = render_splash_screen(w, h)
        sp.save(os.path.join(ddir, "splash.png"), "PNG")
        print(f"  [{dname}] Updated splash ({w}x{h})")

if __name__ == "__main__":
    res_path = r"C:\xampp\htdocs\pw\android\app\src\main\res"
    if os.path.exists(res_path):
        update_android_assets(res_path)
    else:
        print("Android res directory not found:", res_path)
