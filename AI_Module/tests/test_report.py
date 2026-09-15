from analyzers.report_analyzer import analyze_report


description =
"""I lost my Vivo Y31 5G smartphone. 
The phone has a pinkish rose-red back panel
and a triple-camera setup arranged vertically
near the top-left corner. The Vivo logo is
visible on the lower-left side of the back.
The front display has a centered punch-hole camera.
The phone was last seen near Tirupati Bus Stand."""

}

result = analyze_report(description)

print(result)