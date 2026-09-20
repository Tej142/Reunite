"""
LostConnect Normalizer

Converts Description Analyzer, Instant Choice, and Talk-to-AI
data into one common Report JSON structure.

Common output structure:

{
    "object_type": "",
    "attributes": {},
    "location": "",
    "visible_features": [
        {
            "type": "",
            "value": ""
        }
    ],
    "private_features": [
        {
            "type": "",
            "value": ""
        }
    ]
}

This module:
- does NOT call AI
- does NOT call database
- does NOT call Flask
- does NOT generate Digital DNA
"""

from __future__ import annotations

import re
from collections.abc import Mapping
from typing import Any


# ============================================================
# SECURITY
# ============================================================

_BLOCKED_FIELD_PATTERNS = [
    re.compile(r"password", re.IGNORECASE),
    re.compile(r"passcode", re.IGNORECASE),
    re.compile(r"\bpin\b", re.IGNORECASE),
    re.compile(r"unlock", re.IGNORECASE),
    re.compile(r"pattern", re.IGNORECASE),
    re.compile(r"\botp\b", re.IGNORECASE),
    re.compile(r"secret", re.IGNORECASE),
    re.compile(r"login[_\s-]?credential", re.IGNORECASE),
]


def _is_blocked_field(field_name: Any) -> bool:
    """Return True if a field represents sensitive credentials."""

    if field_name is None:
        return False

    text = str(field_name).strip()

    return any(
        pattern.search(text)
        for pattern in _BLOCKED_FIELD_PATTERNS
    )


# ============================================================
# PUBLIC API
# ============================================================

def normalize_report(
    input_data: Any,
    source: str,
    category: str | None = None,
    report_type: str | None = None,
) -> dict[str, Any]:
    """
    Normalize report data from one of three input sources.

    source:
        "description"
        "instant_choice"
        "talk_to_ai"

    Returns:

        {
            "success": True,
            "data": {
                "object_type": "",
                "attributes": {},
                "location": "",
                "visible_features": [],
                "private_features": []
            },
            "warnings": []
        }

    or:

        {
            "success": False,
            "data": None,
            "warnings": [],
            "error": "..."
        }
    """

    warnings: list[str] = []

    # --------------------------------------------------------
    # Validate source
    # --------------------------------------------------------

    valid_sources = {
        "description",
        "instant_choice",
        "talk_to_ai",
    }

    if source not in valid_sources:
        return _error(
            f"Unknown source '{source}'. "
            f"Must be one of: {', '.join(sorted(valid_sources))}.",
            warnings,
        )

    # --------------------------------------------------------
    # Validate input
    # --------------------------------------------------------

    if input_data is None:
        return _error(
            "Input data is None.",
            warnings,
        )

    if not isinstance(input_data, Mapping):
        return _error(
            f"Expected a dictionary, got {type(input_data).__name__}.",
            warnings,
        )

    # --------------------------------------------------------
    # Dispatch
    # --------------------------------------------------------

    try:

        if source == "description":
            data = _normalize_description(
                input_data,
                category,
                report_type,
                warnings,
            )

        elif source == "instant_choice":
            data = _normalize_instant_choice(
                input_data,
                category,
                report_type,
                warnings,
            )

        else:
            data = _normalize_talk_to_ai(
                input_data,
                category,
                report_type,
                warnings,
            )

        return {
            "success": True,
            "data": data,
            "warnings": warnings,
        }

    except Exception as exc:
        return _error(
            str(exc),
            warnings,
        )


# ============================================================
# DESCRIPTION
# ============================================================

def _normalize_description(
    data: Mapping[str, Any],
    category: str | None,
    report_type: str | None,
    warnings: list[str],
) -> dict[str, Any]:
    """
    Description Analyzer already produces the common structure.

    Therefore we preserve its field names instead of converting
    them into another schema.
    """

    object_type = _first_text(
        category,
        data.get("object_type"),
    )

    if not object_type:
        object_type = _first_text(
            data.get("category"),
        )

    attributes = _clean_attributes(
        data.get("attributes")
    )

    location = _clean_text(
        data.get("location")
    )

    visible_features = _clean_features(
        data.get("visible_features"),
        warnings,
    )

    private_features = _clean_private_features(
        data.get("private_features"),
        warnings,
    )

    result = {
        "object_type": object_type,
        "attributes": attributes,
        "location": location,
        "visible_features": visible_features,
        "private_features": private_features,
    }

    return result


# ============================================================
# INSTANT CHOICE
# ============================================================

def _normalize_instant_choice(
    data: Mapping[str, Any],
    category: str | None,
    report_type: str | None,
    warnings: list[str],
) -> dict[str, Any]:
    """
    Convert Instant Choice question-answer data into the
    same structure produced by Description Analyzer.

    Example input:

    {
        "category": "mobile",
        "report_type": "lost",
        "answers": {
            "mobile_brand": "Samsung",
            "mobile_model": "S23",
            "mobile_color": "Black"
        }
    }

    Output:

    {
        "object_type": "mobile",
        "attributes": {
            "brand": "Samsung",
            "model": "S23",
            "color": "Black"
        },
        ...
    }
    """

    # --------------------------------------------------------
    # Get category
    # --------------------------------------------------------

    object_type = _first_text(
        category,
        data.get("category"),
        data.get("object_type"),
    )

    # --------------------------------------------------------
    # Get answers
    # --------------------------------------------------------

    answers = data.get("answers")

    if answers is None:
        # Allow direct question_id -> answer format also.
        answers = data

    if not isinstance(answers, Mapping):
        return _error(
            "'answers' must be a dictionary.",
            warnings,
        )

    attributes: dict[str, Any] = {}

    location = ""

    visible_features: list[dict[str, str]] = []

    private_features: list[dict[str, str]] = []

    # --------------------------------------------------------
    # Process every answer
    # --------------------------------------------------------

    for question_id, raw_value in answers.items():

        question_id = _clean_text(question_id)

        if not question_id:
            continue

        # Meta fields
        if question_id in {
            "category",
            "object_type",
            "report_type",
            "input_type",
        }:
            continue

        # Security check
        if _is_blocked_field(question_id):
            warnings.append(
                f"Question '{question_id}' was excluded "
                "because it represents sensitive credentials."
            )
            continue

        value = _clean_text(raw_value)

        if not value:
            continue

        field_type = _classify_question_id(question_id)

        # ----------------------------------------------------
        # Location
        # ----------------------------------------------------

        if field_type == "location":
            location = value
            continue

        # ----------------------------------------------------
        # Visible feature
        # ----------------------------------------------------

        if field_type == "visible_feature":
            visible_features.append(
                {
                    "type": _humanize_question_id(question_id),
                    "value": value,
                }
            )
            continue

        # ----------------------------------------------------
        # Private feature
        # ----------------------------------------------------

        if field_type == "private_feature":

            if _is_blocked_field(question_id):
                warnings.append(
                    f"Question '{question_id}' was excluded "
                    "because it represents sensitive credentials."
                )
                continue

            private_features.append(
                {
                    "type": _humanize_question_id(question_id),
                    "value": value,
                }
            )
            continue

        # ----------------------------------------------------
        # Normal attribute
        # ----------------------------------------------------

        attribute_name = _attribute_name_from_question_id(
            question_id
        )

        attributes[attribute_name] = value

    return {
        "object_type": object_type,
        "attributes": attributes,
        "location": location,
        "visible_features": visible_features,
        "private_features": private_features,
    }


# ============================================================
# TALK-TO-AI
# ============================================================

def _normalize_talk_to_ai(
    data: Mapping[str, Any],
    category: str | None,
    report_type: str | None,
    warnings: list[str],
) -> dict[str, Any]:
    """
    Normalize structured Talk-to-AI output.

    Expected data can be:

    {
        "category": "mobile",
        "report_type": "lost",
        "answers": {
            "mobile_brand": "Samsung",
            "mobile_model": "S23"
        }
    }

    or directly:

    {
        "mobile_brand": "Samsung",
        "mobile_model": "S23"
    }

    The ResponseParser/TALKAIState is responsible for producing
    valid structured answers before this function is called.
    """

    # If ResponseParser output contains "answers",
    # use that dictionary.
    if "answers" in data:
        answers = data.get("answers")

        if not isinstance(answers, Mapping):
            return _error(
                "'answers' must be a dictionary.",
                warnings,
            )

        talk_data = {
            "category": data.get("category"),
            "report_type": data.get("report_type"),
            "answers": answers,
        }

    else:
        # Also support direct structured data.
        talk_data = data

    return _normalize_instant_choice(
        talk_data,
        category,
        report_type,
        warnings,
    )


# ============================================================
# QUESTION ID CLASSIFICATION
# ============================================================

def _classify_question_id(question_id: str) -> str:
    """
    Determine whether a question represents:
    - location
    - visible feature
    - private feature
    - normal attribute

    This is intentionally generic.
    """

    text = question_id.lower()

    # Location
    location_keywords = {
        "location",
        "place",
        "where",
        "last_seen",
        "last_location",
        "lost_location",
        "found_location",
    }

    if any(keyword in text for keyword in location_keywords):
        return "location"

    # Visible features
    visible_keywords = {
        "scratch",
        "scratches",
        "crack",
        "cracks",
        "dent",
        "dents",
        "sticker",
        "stickers",
        "engraving",
        "mark",
        "marks",
        "damage",
        "damaged",
        "tear",
        "torn",
        "broken",
        "zip",
        "zipper",
        "custom",
        "paint",
        "missing_part",
        "missing_parts",
    }

    if any(keyword in text for keyword in visible_keywords):
        return "visible_feature"

    # Private features
    private_keywords = {
        "imei",
        "serial",
        "lock_screen_message",
        "owner_note",
        "ownership",
    }

    if any(keyword in text for keyword in private_keywords):
        return "private_feature"

    return "attribute"


# ============================================================
# ATTRIBUTE NAME CONVERSION
# ============================================================

def _attribute_name_from_question_id(question_id: str) -> str:
    """
    Convert a Question Engine ID into a clean attribute name.

    Examples:

        mobile_brand
            -> brand

        mobile_model
            -> model

        backpack_material
            -> material

        laptop_ram
            -> ram

    Only the category prefix is removed.
    """

    text = question_id.strip().lower()

    if "_" not in text:
        return text

    parts = text.split("_")

    # Remove the first part because Question Engine IDs
    # normally start with the category.
    #
    # mobile_brand -> brand
    # backpack_color -> color
    # laptop_ram -> ram

    if len(parts) >= 2:
        return "_".join(parts[1:])

    return text


# ============================================================
# FEATURE CLEANING
# ============================================================

def _clean_features(
    value: Any,
    warnings: list[str],
) -> list[dict[str, str]]:

    result: list[dict[str, str]] = []

    if value is None:
        return result

    if isinstance(value, Mapping):
        value = [value]

    elif isinstance(value, str):
        value = [value]

    try:
        items = list(value)
    except TypeError:
        items = [value]

    for item in items:

        # Already structured
        if isinstance(item, Mapping):

            feature_type = _clean_text(
                item.get("type")
            )

            feature_value = _clean_text(
                item.get("value")
            )

            if not feature_value:
                continue

            if _is_blocked_field(feature_type):
                warnings.append(
                    f"Feature '{feature_type}' was excluded "
                    "because it represents sensitive information."
                )
                continue

            result.append(
                {
                    "type": feature_type,
                    "value": feature_value,
                }
            )

        # Plain string
        else:

            feature_value = _clean_text(item)

            if feature_value:
                result.append(
                    {
                        "type": "feature",
                        "value": feature_value,
                    }
                )

    return _dedupe_features(result)


def _clean_private_features(
    value: Any,
    warnings: list[str],
) -> list[dict[str, str]]:

    result: list[dict[str, str]] = []

    if value is None:
        return result

    if isinstance(value, Mapping):
        value = [value]

    elif isinstance(value, str):
        value = [value]

    try:
        items = list(value)
    except TypeError:
        items = [value]

    for item in items:

        if not isinstance(item, Mapping):
            continue

        feature_type = _clean_text(
            item.get("type")
        )

        feature_value = _clean_text(
            item.get("value")
        )

        if not feature_type:
            continue

        # Never allow credentials into final JSON.
        if _is_blocked_field(feature_type):
            warnings.append(
                f"Private field '{feature_type}' was excluded "
                "because it represents sensitive credentials."
            )
            continue

        if not feature_value:
            continue

        result.append(
            {
                "type": feature_type,
                "value": feature_value,
            }
        )

    return _dedupe_features(result)


# ============================================================
# ATTRIBUTE CLEANING
# ============================================================

def _clean_attributes(value: Any) -> dict[str, Any]:

    if not isinstance(value, Mapping):
        return {}

    result: dict[str, Any] = {}

    for key, raw_value in value.items():

        key = _clean_text(key)

        if not key:
            continue

        # Do not store credential fields.
        if _is_blocked_field(key):
            continue

        cleaned_value = _clean_value(raw_value)

        if cleaned_value in (None, "", [], {}):
            continue

        result[key] = cleaned_value

    return result


def _clean_value(value: Any) -> Any:

    if value is None:
        return None

    if isinstance(value, str):
        return _clean_text(value)

    if isinstance(value, Mapping):

        result = {}

        for key, item in value.items():

            key = _clean_text(key)

            if not key:
                continue

            if _is_blocked_field(key):
                continue

            cleaned = _clean_value(item)

            if cleaned not in (None, "", [], {}):
                result[key] = cleaned

        return result

    if isinstance(value, (list, tuple, set)):

        result = []

        for item in value:

            cleaned = _clean_value(item)

            if cleaned not in (None, "", [], {}):
                result.append(cleaned)

        return result

    return value


# ============================================================
# HELPERS
# ============================================================

def _humanize_question_id(question_id: str) -> str:

    text = question_id.strip().lower()

    parts = text.split("_")

    if len(parts) >= 2:
        text = " ".join(parts[1:])

    else:
        text = text.replace("_", " ")

    return text.title()


def _first_text(*values: Any) -> str:

    for value in values:

        text = _clean_text(value)

        if text:
            return text

    return ""


def _clean_text(value: Any) -> str:

    if value is None:
        return ""

    if isinstance(
        value,
        (Mapping, list, tuple, set),
    ):
        return ""

    return " ".join(
        str(value).strip().split()
    )


def _dedupe_features(
    features: list[dict[str, str]],
) -> list[dict[str, str]]:

    seen: set[tuple[str, str]] = set()

    result: list[dict[str, str]] = []

    for feature in features:

        feature_type = feature.get(
            "type",
            "",
        ).strip()

        feature_value = feature.get(
            "value",
            "",
        ).strip()

        key = (
            feature_type.casefold(),
            feature_value.casefold(),
        )

        if not feature_value:
            continue

        if key in seen:
            continue

        seen.add(key)

        result.append(
            {
                "type": feature_type,
                "value": feature_value,
            }
        )

    return result


def _error(
    message: str,
    warnings: list[str],
) -> dict[str, Any]:

    return {
        "success": False,
        "data": None,
        "warnings": warnings,
        "error": message,
    }