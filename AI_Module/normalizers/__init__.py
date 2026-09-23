"""Adapters that convert every report input mode into Common JSON."""

from .normalizer import normalize_report

__all__ = [
    "normalize_report",
]
