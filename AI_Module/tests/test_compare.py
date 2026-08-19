from compare.compare import compare_reports
print('testing started')

current_dna = {
    "category": "Backpack",
    "brand": "Skybags",
    "model": "SB-550",
    "material": "Polyester",
    "location": "SV University",
    "visible_features": [
        "Blue backpack",
        "Two side pockets"
    ],
    "private_features": [
        "Batman sticker inside front pocket"
    ]
}

existing_dna = {
    "category": "Backpack",
    "brand": "Skybags",
    "model": "SB-540",
    "material": "Polyester",
    "location": "SV University",
    "visible_features": [
        "Dark blue backpack",
        "Two side pockets"
    ],
    "private_features": [
        "No sticker"
    ]
}

result = compare_reports(current_dna, existing_dna)

print(result)
print('testing ended')