from datetime import datetime

def format_number(value):
    """Sayıları formatlı göster"""
    try:
        return "{:,}".format(int(value)).replace(",", ".")
    except (ValueError, TypeError):
        return "0"

def format_datetime(value):
    """Tarih/saat formatla"""
    if not value:
        return "Hiç çalışmadı"
    if isinstance(value, str):
        try:
            value = datetime.fromisoformat(value.replace('Z', '+00:00'))
        except ValueError:
            return value
    return value.strftime("%d.%m.%Y %H:%M:%S") 