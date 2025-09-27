const HIJRI_MONTH_NAMES = [
  'Muharram',
  'Safar',
  "Rabi'ul Awal",
  "Rabi'ul Akhir",
  'Jumadil Awal',
  'Jumadil Akhir',
  'Rajab',
  "Sya'ban",
  'Ramadan',
  'Syawal',
  "Zulkaidah",
  'Zulhijjah',
]

const GREGORIAN_EPOCH = 1721425.5
const ISLAMIC_EPOCH = 1948439.5

function mod(a, b) {
  return ((a % b) + b) % b
}

function isGregorianLeap(year) {
  return (year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0)
}

export function gregorianToJulianDay(year, month, day) {
  const a = Math.floor((14 - month) / 12)
  const y = year + 4800 - a
  const m = month + 12 * a - 3
  return day + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045
}

export function julianDayToGregorian(jd) {
  let wjd = Math.floor(jd + 0.5)
  let depoch = wjd - Math.floor(GREGORIAN_EPOCH)
  let quadricent = Math.floor(depoch / 146097)
  let dqc = mod(depoch, 146097)
  let cent = Math.floor(dqc / 36524)
  let dcent = mod(dqc, 36524)
  let quad = Math.floor(dcent / 1461)
  let dquad = mod(dcent, 1461)
  let yindex = Math.floor(dquad / 365)
  let year = quadricent * 400 + cent * 100 + quad * 4 + yindex

  if (!(cent === 4 || yindex === 4)) {
    year += 1
  }

  const yearday = wjd - gregorianToJulianDay(year, 1, 1) + 1
  const leapadj = wjd < gregorianToJulianDay(year, 3, 1)
    ? 0
    : isGregorianLeap(year) ? 1 : 2
  const month = Math.floor(((yearday + leapadj) * 12 + 373) / 367)
  const day = wjd - gregorianToJulianDay(year, month, 1) + 1

  return { year, month, day }
}

function normalizeHijri(year, month) {
  const offset = Math.floor((month - 1) / 12)
  const normalizedMonth = mod(month - 1, 12) + 1
  return { year: year + offset, month: normalizedMonth }
}

export function hijriToJulianDay(year, month, day) {
  const normalized = normalizeHijri(year, month)
  const y = normalized.year
  const m = normalized.month
  return day + Math.ceil(29.5 * (m - 1)) + (y - 1) * 354 + Math.floor((3 + 11 * y) / 30) + ISLAMIC_EPOCH - 1
}

export function julianDayToHijri(jd) {
  const wjd = Math.floor(jd) + 0.5
  const year = Math.floor((30 * (wjd - ISLAMIC_EPOCH) + 10646) / 10631)
  const month = Math.min(12, Math.ceil((wjd - (29 + hijriToJulianDay(year, 1, 1))) / 29.5) + 1)
  const day = wjd - hijriToJulianDay(year, month, 1) + 1
  return { year, month, day }
}

export function hijriMonthLength(year, month) {
  const start = hijriToJulianDay(year, month, 1)
  const { year: nextYear, month: nextMonth } = normalizeHijri(year, month + 1)
  const end = hijriToJulianDay(nextYear, nextMonth, 1)
  return end - start
}

export function shiftHijriMonth(year, month, delta) {
  const totalMonths = (year - 1) * 12 + (month - 1) + delta
  const newYear = Math.floor(totalMonths / 12) + 1
  const newMonth = mod(totalMonths, 12) + 1
  return { year: newYear, month: newMonth }
}

export function hijriToGregorianDate(year, month, day) {
  const jd = hijriToJulianDay(year, month, day)
  const { year: gYear, month: gMonth, day: gDay } = julianDayToGregorian(jd)
  const date = new Date(gYear, gMonth - 1, gDay)
  date.setHours(0, 0, 0, 0)
  return date
}

export function gregorianToHijri(date) {
  const jd = gregorianToJulianDay(date.getFullYear(), date.getMonth() + 1, date.getDate())
  const parts = julianDayToHijri(jd)
  const daysInMonth = hijriMonthLength(parts.year, parts.month)
  return { ...parts, daysInMonth }
}

export function formatHijriMonth(date) {
  const { month, year } = gregorianToHijri(date)
  const name = HIJRI_MONTH_NAMES[month - 1]
  return `${name} ${year} H`
}

export function formatHijriDate(date, { withDayName = true } = {}) {
  const { day, month, year } = gregorianToHijri(date)
  const monthName = HIJRI_MONTH_NAMES[month - 1]
  const base = `${day} ${monthName} ${year} H`
  if (!withDayName) return base
  const dayName = (() => {
    try {
      return date.toLocaleDateString('id-ID', { weekday: 'long' })
    } catch (e) {
      return date.toLocaleDateString(undefined, { weekday: 'long' })
    }
  })()
  return `${dayName}, ${base}`
}

export function hijriMonthRange(date) {
  const parts = gregorianToHijri(date)
  const start = hijriToGregorianDate(parts.year, parts.month, 1)
  const end = hijriToGregorianDate(parts.year, parts.month, parts.daysInMonth)
  end.setHours(23, 59, 59, 999)
  return {
    start,
    end,
    hijriMonth: parts.month,
    hijriYear: parts.year,
    daysInMonth: parts.daysInMonth,
  }
}

export { HIJRI_MONTH_NAMES }
