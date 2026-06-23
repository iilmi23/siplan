import { SummaryItem } from "@/types/models";

export const MONTH_LABELS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

export const MONTH_ALIASES: Record<string, number> = {
    JAN: 0, FEB: 1, MAR: 2, APR: 3, MAY: 4, JUN: 5,
    JUL: 6, AUG: 7, SEP: 8, OCT: 9, NOV: 10, DEC: 11,
};

export const fmtDate = (d: any) => {
    if (!d) return "-";
    const p = new Date(d);
    if (isNaN(p.getTime())) return "-";
    return `${p.getMonth() + 1}/${p.getDate()}`;
};

export const fmtEtdDate = (d: any) => {
    if (!d) return "";
    const p = parseDateOnly(d);
    if (!p) return "";
    return `${p.getDate()}-${MONTH_LABELS[p.getMonth()]}`;
};

export const normalizeWeek = (week: any) => {
    if (week === null || typeof week === "undefined" || week === "") return "";
    if (week === "TOT") return "TOT";
    const match = String(week).match(/\d+/);
    return match ? Number(match[0]) : week;
};

export const periodKey = (etd: any, eta: any, week: any) => `${etd || ""}|${eta || ""}|${normalizeWeek(week)}`;
export const defaultPeriodKey = (etd: any, week: any) => `${etd || ""}|${normalizeWeek(week)}`;
export const ynaPeriodKey = (etd: any) => `${etd || ""}`;
export const hasFallbackEta = (item: SummaryItem) => item?.extra?.eta_fallback === true;
export const orderTypeValue = (value: any) => (value || "FORECAST").toUpperCase();
export const defaultPivotKey = (type: any, etd: any, week: any) => `${orderTypeValue(type)}|${defaultPeriodKey(etd, week)}`;
export const defaultItemPivotKey = (item: SummaryItem) => defaultPivotKey(item.order_type, item.etd, item.week);
export const rowsQty = (rows: SummaryItem[]) => rows.reduce((sum, item) => sum + Number(item.qty || 0), 0);
export const DEFAULT_PERIOD_WIDTH = 76;

export const parseDateOnly = (value: any) => {
    if (!value) return null;
    const [year, month, day] = String(value).slice(0, 10).split("-").map(Number);
    if (!year || !month || !day) return null;
    return new Date(year, month - 1, day);
};

export const monthKeyFromPeriod = (etd: any, month: any) => {
    const value = String(month || "").trim();
    const yearMonth = value.match(/^(\d{4})-(\d{1,2})/);
    if (yearMonth) return `${yearMonth[1]}-${String(Number(yearMonth[2])).padStart(2, "0")}`;

    const alias = value.slice(0, 3).toUpperCase();
    if (alias in MONTH_ALIASES) {
        const monthNum = MONTH_ALIASES[alias] + 1;
        const monthStr = String(monthNum).padStart(2, "0");
        const etdDate = parseDateOnly(etd);
        const year = etdDate ? etdDate.getFullYear() : new Date().getFullYear();
        return `${year}-${monthStr}`;
    }

    const etdDate = parseDateOnly(etd);
    if (etdDate) return `${etdDate.getFullYear()}-${String(etdDate.getMonth() + 1).padStart(2, "0")}`;

    return value || "unknown";
};

export const monthLabel = (month: any) => {
    const value = String(month || "").trim();
    const yearMonth = value.match(/^(\d{4})-(\d{1,2})/);
    if (yearMonth) {
        const year = yearMonth[1].slice(-2);
        const monthIndex = Number(yearMonth[2]) - 1;
        return `${MONTH_LABELS[monthIndex] || value}-${year}`;
    }

    const alias = value.slice(0, 3).toUpperCase();
    if (alias in MONTH_ALIASES) return MONTH_LABELS[MONTH_ALIASES[alias]];

    return value || "-";
};

export const addDays = (date: Date, days: number) => {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
};

export const addMonthsNoOverflow = (date: Date, months: number) => {
    const next = new Date(date);
    next.setMonth(next.getMonth() + months, 1);
    return next;
};

export const daysInMonth = (date: Date) => new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();

export const startOfMondayWeek = (date: Date) => {
    const day = date.getDay() || 7;
    return addDays(date, 1 - day);
};

export const diffDays = (start: any, end: any) => Math.round((end - start) / 86400000);

export const calculateYNAWeek = (etd: any) => {
    const date = parseDateOnly(etd);
    if (!date) return "";

    const weekMonday = startOfMondayWeek(date);
    const remainingDaysInMonth = daysInMonth(weekMonday) - weekMonday.getDate() + 1;
    const weekMonthDate = remainingDaysInMonth <= 1 ? addMonthsNoOverflow(weekMonday, 1) : new Date(weekMonday);
    const targetYear = weekMonthDate.getFullYear();
    const targetMonth = weekMonthDate.getMonth();

    const firstOfMonth = new Date(targetYear, targetMonth, 1);
    let firstMonday = startOfMondayWeek(firstOfMonth);

    if (firstMonday.getMonth() !== targetMonth) {
        const prevMonthRemaining = daysInMonth(firstMonday) - firstMonday.getDate() + 1;
        if (prevMonthRemaining > 1) {
            firstMonday = addDays(firstMonday, 7);
        }
    }

    const weekNumber = Math.floor(diffDays(firstMonday, weekMonday) / 7) + 1;
    return Math.min(weekNumber, 5);
};

export const displayWeek = (item: SummaryItem, isYNAFormat: boolean) => isYNAFormat ? calculateYNAWeek(item.etd) : normalizeWeek(item.week);
export const monthKeyFromDate = (value: any) => value ? String(value).slice(0, 7) : "unknown";

export const ynaMonthKey = (item: SummaryItem) => {
    const value = String(item.month || "").trim();
    const yearMonth = value.match(/^(\d{4})-(\d{1,2})/);
    if (yearMonth) return `${yearMonth[1]}-${String(Number(yearMonth[2])).padStart(2, "0")}`;

    const alias = value.slice(0, 3).toUpperCase();
    if (alias in MONTH_ALIASES) {
        const monthNum = MONTH_ALIASES[alias] + 1;
        const monthStr = String(monthNum).padStart(2, "0");
        let year = item.year;
        if (!year) {
            const etdDate = parseDateOnly(item.etd);
            year = etdDate ? etdDate.getFullYear() : new Date().getFullYear();
        }
        return `${year}-${monthStr}`;
    }

    return monthKeyFromDate(item.etd || item.eta || item.month);
};

export const ynaPeriodValue = (qty: Record<string, number>, period: any, periods: any[]) => {
    if (period.isPad) return 0;
    if (!period.isTotal) return qty[period.key] || 0;

    return periods
        .filter((item) => item.month === period.month && !item.isPad && !item.isTotal)
        .reduce((sum, item) => sum + (qty[item.key] || 0), 0);
};

export function formatUploadTimestamp(value: any) {
    if (!value) return "-";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "-";

    return date.toLocaleString("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

export function dateInputValue(value: any) {
    if (!value) return "";

    return String(value).slice(0, 10);
}
