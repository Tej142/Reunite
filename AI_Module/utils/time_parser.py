import re
from datetime import datetime, timedelta
from typing import Optional, Dict, Any

WEEKDAYS = {
    'monday': 0, 'mon': 0,
    'tuesday': 1, 'tue': 1,
    'wednesday': 2, 'wed': 2,
    'thursday': 3, 'thu': 3,
    'friday': 4, 'fri': 4,
    'saturday': 5, 'sat': 5,
    'sunday': 6, 'sun': 6
}

MONTHS = {
    'january': 1, 'jan': 1,
    'february': 2, 'feb': 2,
    'march': 3, 'mar': 3,
    'april': 4, 'apr': 4,
    'may': 5,
    'june': 6, 'jun': 6,
    'july': 7, 'jul': 7,
    'august': 8, 'aug': 8,
    'september': 9, 'sep': 9, 'sept': 9,
    'october': 10, 'oct': 10,
    'november': 11, 'nov': 11,
    'december': 12, 'dec': 12
}


def parse_temporal_expression(text: str, reference_datetime: Optional[datetime] = None) -> Dict[str, Any]:
    """
    Smart Temporal Resolver.
    Parses natural language relative and absolute date/time expressions
    against server reference datetime and returns resolved YYYY-MM-DD and HH:MM:SS.
    """
    if not reference_datetime:
        reference_datetime = datetime.now()

    ref_date = reference_datetime.date()
    clean_text = str(text or "").strip()
    low_text = clean_text.lower()

    resolved_date = ref_date
    resolved_time = None
    raw_match = None
    is_relative = False
    days_ago = 0

    # 1. Day before yesterday / 2 days ago
    if "day before yesterday" in low_text or "day before yesturday" in low_text:
        resolved_date = ref_date - timedelta(days=2)
        raw_match = "day before yesterday"
        is_relative = True
        days_ago = 2
    elif re.search(r'\b2\s*days?\s*ago\b', low_text):
        resolved_date = ref_date - timedelta(days=2)
        raw_match = "2 days ago"
        is_relative = True
        days_ago = 2
    elif re.search(r'\b(\d+)\s*days?\s*ago\b', low_text):
        m = re.search(r'\b(\d+)\s*days?\s*ago\b', low_text)
        num_days = int(m.group(1))
        resolved_date = ref_date - timedelta(days=num_days)
        raw_match = m.group(0)
        is_relative = True
        days_ago = num_days

    # 2. Yesterday
    elif "yesterday" in low_text or "yesturday" in low_text or "last night" in low_text or "yesterday night" in low_text:
        resolved_date = ref_date - timedelta(days=1)
        raw_match = "yesterday"
        is_relative = True
        days_ago = 1
        if "night" in low_text:
            resolved_time = "21:00:00"

    # 3. Today / Tonight / This morning / This afternoon
    elif "today" in low_text or "this morning" in low_text or "this afternoon" in low_text or "this evening" in low_text or "tonight" in low_text:
        resolved_date = ref_date
        raw_match = "today"
        is_relative = True
        days_ago = 0
        if "morning" in low_text:
            resolved_time = "09:30:00"
        elif "afternoon" in low_text:
            resolved_time = "14:00:00"
        elif "evening" in low_text:
            resolved_time = "18:00:00"
        elif "tonight" in low_text:
            resolved_time = "20:30:00"

    # 4. Last [Weekday] (e.g. "last friday", "last monday")
    elif re.search(r'\blast\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)\b', low_text):
        m = re.search(r'\blast\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)\b', low_text)
        weekday_name = m.group(1)
        target_wd = WEEKDAYS[weekday_name]
        current_wd = ref_date.weekday()
        days_back = (current_wd - target_wd) % 7
        if days_back == 0:
            days_back = 7
        resolved_date = ref_date - timedelta(days=days_back)
        raw_match = m.group(0)
        is_relative = True
        days_ago = days_back

    # 5. Explicit ISO Date (YYYY-MM-DD)
    elif re.search(r'\b(\d{4})-(\d{1,2})-(\d{1,2})\b', low_text):
        m = re.search(r'\b(\d{4})-(\d{1,2})-(\d{1,2})\b', low_text)
        try:
            resolved_date = datetime(int(m.group(1)), int(m.group(2)), int(m.group(3))).date()
            raw_match = m.group(0)
            days_ago = (ref_date - resolved_date).days
        except Exception:
            pass

    # 6. Explicit DD/MM/YYYY or DD-MM-YYYY
    elif re.search(r'\b(\d{1,2})[/-](\d{1,2})[/-](\d{4})\b', low_text):
        m = re.search(r'\b(\d{1,2})[/-](\d{1,2})[/-](\d{4})\b', low_text)
        try:
            day = int(m.group(1))
            month = int(m.group(2))
            year = int(m.group(3))
            resolved_date = datetime(year, month, day).date()
            raw_match = m.group(0)
            days_ago = (ref_date - resolved_date).days
        except Exception:
            pass

    # 7. Textual Date: e.g. "3rd Oct", "October 3rd", "3 Oct 2026"
    elif re.search(r'\b(\d{1,2})(?:st|nd|rd|th)?\s+(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec)\b(?:\s+(\d{4}))?', low_text):
        m = re.search(r'\b(\d{1,2})(?:st|nd|rd|th)?\s+(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec)\b(?:\s+(\d{4}))?', low_text)
        day = int(m.group(1))
        month = MONTHS[m.group(2)]
        year = int(m.group(3)) if m.group(3) else ref_date.year
        try:
            resolved_date = datetime(year, month, day).date()
            raw_match = m.group(0)
            days_ago = (ref_date - resolved_date).days
        except Exception:
            pass
    elif re.search(r'\b(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec)\s+(\d{1,2})(?:st|nd|rd|th)?\b(?:\s+(\d{4}))?', low_text):
        m = re.search(r'\b(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec)\s+(\d{1,2})(?:st|nd|rd|th)?\b(?:\s+(\d{4}))?', low_text)
        month = MONTHS[m.group(1)]
        day = int(m.group(2))
        year = int(m.group(3)) if m.group(3) else ref_date.year
        try:
            resolved_date = datetime(year, month, day).date()
            raw_match = m.group(0)
            days_ago = (ref_date - resolved_date).days
        except Exception:
            pass

    # Extract Time of Day (e.g. "3:30 pm", "15:00", "at 4 pm", "11:15 AM")
    time_match = re.search(r'\b(?:at|around|@)?\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b', low_text)
    if time_match:
        hr = int(time_match.group(1))
        mn = int(time_match.group(2)) if time_match.group(2) else 0
        ampm = time_match.group(3).lower()
        if ampm == 'pm' and hr < 12:
            hr += 12
        elif ampm == 'am' and hr == 12:
            hr = 0
        resolved_time = f"{hr:02d}:{mn:02d}:00"
    elif re.search(r'\b(?:at|around|@)?\s*([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?\b', low_text):
        tm = re.search(r'\b(?:at|around|@)?\s*([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?\b', low_text)
        hr = int(tm.group(1))
        mn = int(tm.group(2))
        sec = int(tm.group(3)) if tm.group(3) else 0
        resolved_time = f"{hr:02d}:{mn:02d}:{sec:02d}"

    # Format Human Display
    date_str = resolved_date.strftime("%Y-%m-%d")
    if days_ago == 0:
        display_label = "Today (" + resolved_date.strftime("%b %d, %Y") + ")"
    elif days_ago == 1:
        display_label = "Yesterday (" + resolved_date.strftime("%b %d, %Y") + ")"
    elif days_ago > 1 and days_ago <= 7:
        display_label = f"{days_ago} days ago (" + resolved_date.strftime("%b %d, %Y") + ")"
    else:
        display_label = resolved_date.strftime("%B %d, %Y")

    if resolved_time:
        try:
            t_obj = datetime.strptime(resolved_time, "%H:%M:%S")
            display_label += " at " + t_obj.strftime("%I:%M %p")
        except Exception:
            pass

    return {
        "success": True,
        "resolved_date": date_str,
        "resolved_time": resolved_time or "",
        "days_ago": max(0, days_ago),
        "is_relative": is_relative,
        "raw_expression": raw_match or clean_text,
        "formatted_display": display_label,
        "server_timestamp": reference_datetime.strftime("%Y-%m-%d %H:%M:%S")
    }
