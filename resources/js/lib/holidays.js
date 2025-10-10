import { gregorianToHijri } from './hijri.js'

export const GREGORIAN_MONTH_NAMES = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
]

export function matchesHoliday(date, holiday) {
  if (!holiday)
    return false
  const gMonth = date.getMonth() + 1
  const gDay = date.getDate()
  const gYear = date.getFullYear()

  if (
    Number.isInteger(holiday.gregorian_month)
    && Number.isInteger(holiday.gregorian_day)
    && holiday.gregorian_month === gMonth
    && holiday.gregorian_day === gDay
  ) {
    if (!holiday.gregorian_year || holiday.gregorian_year === gYear) {
      return true
    }
  }

  if (Number.isInteger(holiday.hijri_month) && Number.isInteger(holiday.hijri_day)) {
    const hijri = gregorianToHijri(date)
    if (
      holiday.hijri_month === hijri.month
      && holiday.hijri_day === hijri.day
      && (!holiday.hijri_year || holiday.hijri_year === hijri.year)
    ) {
      return true
    }
  }

  return false
}

export function holidaysForDate(date, holidays = []) {
  return holidays.filter(holiday => matchesHoliday(date, holiday))
}

export function formatGregorianPart(holiday) {
  if (!Number.isInteger(holiday?.gregorian_month) || !Number.isInteger(holiday?.gregorian_day)) {
    return ''
  }
  const monthName = GREGORIAN_MONTH_NAMES[holiday.gregorian_month - 1]
  const day = String(holiday.gregorian_day).padStart(2, '0')
  const year = holiday.gregorian_year ? ` ${holiday.gregorian_year}` : ''
  return `${day} ${monthName}${year}`
}

export function formatHijriPart(holiday, monthNames = []) {
  if (!Number.isInteger(holiday?.hijri_month) || !Number.isInteger(holiday?.hijri_day)) {
    return ''
  }
  const names = monthNames.length ? monthNames : []
  const monthName = names[holiday.hijri_month - 1] ?? `Bulan ${holiday.hijri_month}`
  const day = String(holiday.hijri_day)
  const year = holiday.hijri_year ? ` ${holiday.hijri_year} H` : ''
  return `${day} ${monthName}${year}`
}

export function describeHoliday(holiday, monthNames = []) {
  const parts = []
  const gregorian = formatGregorianPart(holiday)
  if (gregorian) {
    parts.push(`${gregorian} (Gregorian)${holiday.gregorian_year ? '' : ' (tahunan)'}`)
  }
  const hijri = formatHijriPart(holiday, monthNames)
  if (hijri) {
    parts.push(`${hijri} (Hijriah)${holiday.hijri_year ? '' : ' (tahunan)'}`)
  }
  return parts.join(' | ')
}
