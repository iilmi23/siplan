import { useMemo } from "react";
import { SummaryItem } from "@/types/models";
import {
    fmtDate,
    fmtEtdDate,
    normalizeWeek,
    periodKey,
    defaultPeriodKey,
    ynaPeriodKey,
    hasFallbackEta,
    orderTypeValue,
    defaultPivotKey,
    defaultItemPivotKey,
    rowsQty,
    DEFAULT_PERIOD_WIDTH,
    monthKeyFromPeriod,
    monthLabel,
    displayWeek,
    ynaMonthKey,
    ynaPeriodValue,
} from "./helpers";

export interface SummaryPeriod {
    key?: string;
    month?: string;
    type?: string;
    etd?: string;
    eta?: string;
    week?: string | number;
    etdFmt?: string;
    etaFmt?: string;
    etd_raw?: string;
    eta_raw?: string;
    isPad?: boolean;
    isTotal?: boolean;
}

export interface PivotCellSelection {
    title: string;
    subtitle: string;
    rows: SummaryItem[];
    totalQty: number;
}

type QtyBucket = Record<string, number>;
type GroupedEntry = [string, { meta?: SummaryItem; qty: QtyBucket }];

interface PivotPreviewProps {
    data: SummaryItem[];
    customer?: string;
    onOpenCell: (selection: PivotCellSelection) => void;
    runningWeek?: Record<string, any> | null;
    periodsPerMonth: number;
}

export default function PivotPreview({
    data,
    customer,
    onOpenCell,
    runningWeek,
    periodsPerMonth,
}: PivotPreviewProps) {
    const isPeriodRunningWeek = (p: SummaryPeriod) => {
        if (p.isPad || p.isTotal || !p.etd_raw || !runningWeek) return false;
        const etdStr = p.etd_raw.slice(0, 10);
        return etdStr >= runningWeek.week_start && etdStr <= runningWeek.end_date;
    };

    const isYNAFormat = customer?.toUpperCase() === 'YNA';

    const periods = useMemo(() => {
        const byMonth: Record<string, Record<string, SummaryPeriod>> = {};
        data.forEach((item) => {
            const month = ynaMonthKey(item);
            const key = ynaPeriodKey(item.etd);
            byMonth[month] = byMonth[month] || {};
            if (!byMonth[month][key]) {
                byMonth[month][key] = {
                    key,
                    month,
                    etd: item.etd,
                    eta: isYNAFormat && hasFallbackEta(item) ? "" : item.eta,
                    week: displayWeek(item, isYNAFormat),
                    etdFmt: fmtDate(item.etd),
                    etaFmt: isYNAFormat && hasFallbackEta(item) ? "" : fmtDate(item.eta),
                    etd_raw: item.etd,
                };
            } else if (!byMonth[month][key].eta && item.eta && !hasFallbackEta(item)) {
                byMonth[month][key].eta = item.eta;
                byMonth[month][key].etaFmt = fmtDate(item.eta);
            }
        });

        return Object.keys(byMonth).sort().flatMap((month) => {
            const actualPeriods = Object.values(byMonth[month]).sort((a, b) => (a.etd || "").localeCompare(b.etd || ""));
            const paddedPeriods = [...actualPeriods];

            while (paddedPeriods.length < periodsPerMonth) {
                paddedPeriods.push({
                    key: `pad|${month}|${paddedPeriods.length + 1}`,
                    month,
                    etd: "",
                    eta: "",
                    week: paddedPeriods.length + 1,
                    etdFmt: "",
                    etaFmt: "",
                    isPad: true,
                });
            }

            paddedPeriods.push({
                key: `tot|${month}`,
                month,
                etd: "",
                eta: "",
                week: "TOT",
                etdFmt: "TOT",
                etaFmt: "",
                isTotal: true,
            });

            return paddedPeriods;
        });
    }, [data, isYNAFormat, periodsPerMonth]);

    const grouped = useMemo(() => {
        const g: Record<string, { meta: SummaryItem; qty: QtyBucket }> = {};
        data.forEach((item) => {
            const pn = item.assy_number || "-";
            if (!g[pn]) g[pn] = { meta: item, qty: {} };
            const key = isYNAFormat ? ynaPeriodKey(item.etd) : periodKey(item.etd, item.eta, item.week);
            g[pn].qty[key] = (g[pn].qty[key] || 0) + Number(item.qty || 0);
        });

        return Object.entries(g).sort(([a], [b]) => a.localeCompare(b, undefined, { numeric: true })) as GroupedEntry[];
    }, [data, isYNAFormat]);

    const colTotals = useMemo(() => {
        const totals: QtyBucket = {};
        periods.forEach((p) => {
            totals[p.key!] = grouped.reduce((sum, [, v]) => sum + ynaPeriodValue(v.qty, p, periods), 0);
        });
        return totals;
    }, [periods, grouped]);

    const openYnaPeriod = (period: SummaryPeriod) => {
        if (period.isPad || period.isTotal) return;

        const rows = data.filter((item) =>
            ynaPeriodKey(item.etd) === period.key
        );

        if (rows.length === 0) return;

        onOpenCell({
            title: "Review Period",
            subtitle: `ETD ${period.etdFmt || "-"} | ETA ${period.etaFmt || "-"} | Week ${period.week || "-"}`,
            rows,
            totalQty: rowsQty(rows),
        });
    };

    const periodsWithTotals = useMemo(() => {
        const byTypeMonth: Record<string, Record<string, Record<string, SummaryPeriod>>> = {};

        data.forEach((item) => {
            const type = (item.order_type || 'FORECAST').toUpperCase();
            const month = monthKeyFromPeriod(item.etd, item.month);
            const key = defaultPeriodKey(item.etd, item.week);

            byTypeMonth[type] = byTypeMonth[type] || {};
            byTypeMonth[type][month] = byTypeMonth[type][month] || {};

            if (!byTypeMonth[type][month][key]) {
                byTypeMonth[type][month][key] = {
                    type,
                    month,
                    week: normalizeWeek(item.week),
                    etd: item.etd ? fmtEtdDate(item.etd) : '',
                    eta: item.eta ? fmtDate(item.eta) : '',
                    etd_raw: item.etd || '',
                    eta_raw: item.eta || '',
                };
            }
        });

        const sortedTypes = ['FIRM', 'FORECAST'].filter((t) => byTypeMonth[t]);
        const result: SummaryPeriod[] = [];

        sortedTypes.forEach((type) => {
            const months = Object.keys(byTypeMonth[type]).sort();
            months.forEach((month) => {
                const periods = Object.values(byTypeMonth[type][month]).sort((a, b) => (a.etd_raw || '').localeCompare(b.etd_raw || ''));
                let weekCount = 0;

                periods.forEach((period) => {
                    result.push(period);
                    weekCount += 1;
                });

                while (weekCount < periodsPerMonth) {
                    result.push({
                        type,
                        month,
                        week: '',
                        etd: '',
                        eta: '',
                        etd_raw: '',
                        eta_raw: '',
                    });
                    weekCount += 1;
                }

                result.push({
                    type,
                    month,
                    week: 'TOT',
                    etd: '',
                    eta: '',
                    etd_raw: '',
                    eta_raw: '',
                });
            });
        });

        return result;
    }, [data, periodsPerMonth]);

    const groups = useMemo(() => {
        const result = [];
        let current: { key: string; type?: string; month?: string; start: number; end: number } | null = null;

        periodsWithTotals.forEach((p, idx) => {
            const key = `${p.type}|${p.month}`;
            const colIndex = idx + 4;

            if (!current || current.key !== key) {
                if (current) result.push(current);
                current = { key, type: p.type, month: p.month, start: colIndex, end: colIndex };
            } else {
                current.end = colIndex;
            }
        });

        if (current) result.push(current);
        return result;
    }, [periodsWithTotals]);

    const groupedDefault = useMemo(() => {
        const g: Record<string, { meta: SummaryItem; qty: QtyBucket }> = {};
        data.forEach((item) => {
            const pn = item.assy_number || "-";
            if (!g[pn]) g[pn] = { meta: item, qty: {} };
            const key = defaultItemPivotKey(item);
            g[pn].qty[key] = (g[pn].qty[key] || 0) + Number(item.qty || 0);
        });
        return Object.entries(g).sort(([a], [b]) => a.localeCompare(b, undefined, { numeric: true })) as GroupedEntry[];
    }, [data]);

    const periodKeysForTotal = (period: SummaryPeriod) => periodsWithTotals
        .filter((item) => item.type === period.type && item.month === period.month && item.week !== 'TOT')
        .map((item) => defaultPivotKey(item.type, item.etd_raw, item.week));

    const openDefaultPeriod = (period: SummaryPeriod) => {
        if (period.week === 'TOT') return;

        const key = defaultPivotKey(period.type, period.etd_raw, period.week);
        const rows = data.filter((item) =>
            defaultItemPivotKey(item) === key
        );

        if (rows.length === 0) return;

        onOpenCell({
            title: "Review Period",
            subtitle: `${orderTypeValue(period.type)} | ${monthLabel(period.month)} | ETD ${period.etd || "-"} | Week ${period.week || "-"}`,
            rows,
            totalQty: rowsQty(rows),
        });
    };

    if (grouped.length === 0) {
        return <div className="p-8 text-center text-gray-400 text-sm">No data</div>;
    }

    // YNA format: Simple pivot table
    if (isYNAFormat) {
        return (
            <div className="summary-pivot-scroll relative isolate z-0 overflow-x-auto border-y border-[#b7d9c4] bg-[#f8fcfa] dark:border-slate-700 dark:bg-[#0f172a]">
                <table className="border-separate border-spacing-0 bg-[#f8fcfa] font-mono text-[9.5px] dark:bg-[#0f172a]" style={{ minWidth: `${173 + periods.length * 52}px` }}>
                    <thead className="relative z-40">
                        <tr>
                            <th
                                rowSpan={3}
                                className="sticky left-0 z-50 h-[22px] border border-[#145233] px-1.5 py-0.5 text-center text-white text-[9px] tracking-wider dark:border-emerald-950"
                                style={{ background: "#1D4D2A", minWidth: 28, width: 28, top: 0 }}
                            >
                                NO
                            </th>
                            <th
                                rowSpan={3}
                                className="sticky z-50 h-[22px] border border-[#145233] px-2 py-0.5 text-center text-white text-[9px] tracking-wider dark:border-emerald-950"
                                style={{ background: "#1D4D2A", minWidth: 145, width: 145, left: 28, top: 0 }}
                            >
                                ASSY NO
                            </th>
                            <th
                                className="sticky z-50 h-[22px] border border-[#145233] px-1.5 py-0.5 text-center text-white text-[9px] tracking-wider dark:border-emerald-950"
                                style={{ background: "#1D6F42", left: 173, minWidth: 40, width: 40, top: 0 }}
                            >
                                ETD
                            </th>
                            {periods.map((p) => {
                                const isRunning = isPeriodRunningWeek(p);
                                return (
                                    <th
                                        key={`etd-${p.key}`}
                                        onClick={() => openYnaPeriod(p)}
                                        title={!p.isTotal && !p.isPad ? "Click to review ETD/ETA rows" : undefined}
                                        className={`sticky z-40 h-[22px] border border-[#145233] px-1 py-0.5 text-center text-[9px] font-semibold dark:border-emerald-950 ${!p.isTotal && !p.isPad ? "cursor-pointer hover:ring-1 hover:ring-white/50" : ""}`}
                                        style={{
                                            background: isRunning ? "#FF0000" : (p.isTotal ? "#2D5A3D" : "#1D6F42"),
                                            color: isRunning ? "#000000" : "#ffffff",
                                            minWidth: 52,
                                            width: 52,
                                            top: 0
                                        }}
                                    >
                                        {p.etdFmt}
                                    </th>
                                );
                            })}
                        </tr>
                        <tr>
                            <th
                                className="sticky z-50 h-[22px] border border-[#145233] px-1.5 py-0.5 text-center text-white text-[9px] tracking-wider dark:border-emerald-950"
                                style={{ background: "#2E9E5E", left: 173, minWidth: 40, width: 40, top: 22 }}
                            >
                                ETA
                            </th>
                            {periods.map((p) => (
                                <th
                                    key={`eta-${p.key}`}
                                    onClick={() => openYnaPeriod(p)}
                                    title={!p.isTotal && !p.isPad ? "Click to review ETD/ETA rows" : undefined}
                                    className={`sticky z-40 h-[22px] border border-[#145233] px-1 py-0.5 text-center text-white text-[9px] font-semibold dark:border-emerald-950 ${!p.isTotal && !p.isPad ? "cursor-pointer hover:ring-1 hover:ring-white/50" : ""}`}
                                    style={{ background: p.isTotal ? "#2D5A3D" : "#2E9E5E", minWidth: 52, width: 52, top: 22 }}
                                >
                                    {p.etaFmt}
                                </th>
                            ))}
                        </tr>
                        <tr>
                            <th
                                className="sticky z-50 h-[22px] border border-[#145233] px-1.5 py-0.5 text-center text-white text-[9px] tracking-wider dark:border-emerald-950"
                                style={{ background: "#237A50", left: 173, minWidth: 40, width: 40, top: 44 }}
                            >
                                WEEK
                            </th>
                            {periods.map((p) => (
                                <th
                                    key={`week-${p.key}`}
                                    onClick={() => openYnaPeriod(p)}
                                    title={!p.isTotal && !p.isPad ? "Click to review ETD/ETA rows" : undefined}
                                    className={`sticky z-40 h-[22px] border border-[#145233] px-1 py-0.5 text-center text-white text-[9px] font-semibold dark:border-emerald-950 ${!p.isTotal && !p.isPad ? "cursor-pointer hover:ring-1 hover:ring-white/50" : ""}`}
                                    style={{ background: p.isTotal ? "#2D5A3D" : "#237A50", minWidth: 52, width: 52, top: 44 }}
                                >
                                    {p.isTotal ? "" : (p.week || "-")}
                                </th>
                            ))}
                        </tr>
                    </thead>

                    <tbody>
                        {grouped.map(([assyNumber, { qty }], idx) => {
                            return (
                                <tr key={assyNumber} className="odd:bg-[#f0faf4] even:bg-[#d6efdf] dark:odd:bg-[#10251b] dark:even:bg-[#14301f]">
                                    <td
                                        className="sticky left-0 z-20 border border-[#1f5f41] px-2 py-1 text-center font-bold text-white dark:border-slate-700"
                                        style={{ background: "#2D5A3D", minWidth: 28, width: 28 }}
                                    >
                                        {idx + 1}
                                    </td>
                                    <td
                                        className="sticky z-20 truncate border border-[#1f5f41] px-2.5 py-1 font-bold text-white dark:border-slate-700"
                                        style={{ background: "#2D5A3D", left: 28, minWidth: 145, width: 145, maxWidth: 145 }}
                                        title={assyNumber}
                                    >
                                        {assyNumber}
                                    </td>
                                    <td
                                        className="sticky z-20 border border-[#1f5f41] px-2 py-1 text-center text-[10px] dark:border-slate-700"
                                        style={{ background: "#3A6B4E", color: "#D4EDE0", left: 173, minWidth: 40, width: 40 }}
                                    >
                                        QTY
                                    </td>
                                    {periods.map((p) => {
                                        const val = ynaPeriodValue(qty, p, periods);
                                        return (
                                            <td
                                                key={p.key}
                                                className="border border-[#b7d9c4] px-2 py-1 text-right text-[#0D4F2C] dark:border-slate-700 dark:text-emerald-100"
                                                style={{
                                                    color: val === 0 ? "var(--sr-zero, #93a99b)" : undefined,
                                                    fontWeight: p.isTotal || val !== 0 ? 700 : 400,
                                                    background: p.isTotal ? "rgba(125, 207, 181, 0.55)" : "transparent",
                                                }}
                                            >
                                                {val.toLocaleString()}
                                            </td>
                                        );
                                    })}
                                </tr>
                            );
                        })}
                    </tbody>

                    <tfoot>
                        <tr>
                            <td
                                className="sticky left-0 z-20 border border-[#2E7D52] px-2 py-1.5 text-center font-bold text-white dark:border-slate-700"
                                style={{ background: "#0F3320" }}
                            />
                            <td
                                className="sticky z-20 border border-[#2E7D52] px-3 py-1.5 text-left font-bold text-white dark:border-slate-700"
                                style={{ background: "#0F3320", left: 28 }}
                            >
                                TOTAL
                            </td>
                            <td
                                className="sticky z-20 border border-[#2E7D52] px-3 py-1.5 dark:border-slate-700"
                                style={{ background: "#0F3320", left: 173 }}
                            />
                            {periods.map((p) => {
                                const val = colTotals[p.key!] || 0;
                                return (
                                    <td
                                        key={p.key}
                                        className="border border-[#2E7D52] px-2 py-1.5 text-right font-bold text-white dark:border-slate-700"
                                        style={{ background: "#0F3320" }}
                                    >
                                        {val.toLocaleString()}
                                    </td>
                                );
                            })}
                        </tr>
                    </tfoot>
                </table>
            </div>
        );
    }

    return (
        <div className="summary-pivot-scroll relative isolate z-0 overflow-x-auto border-y border-[#b7d9c4] bg-[#f8fcfa] dark:border-slate-700 dark:bg-[#0f172a]">
            <table className="border-separate border-spacing-0 bg-[#f8fcfa] font-mono text-[9.5px] dark:bg-[#0f172a]" style={{ minWidth: `${243 + periodsWithTotals.length * DEFAULT_PERIOD_WIDTH}px` }}>
                <thead className="relative z-40">
                    <tr>
                        <th
                            rowSpan={4}
                            className="sticky left-0 z-50 h-[22px] border border-[#145233] px-1.5 py-0.5 text-center text-[9px] tracking-wider text-white dark:border-emerald-950"
                            style={{ background: "#1D4D2A", minWidth: 28, width: 28, top: 0 }}
                        >
                            NO
                        </th>
                        <th
                            rowSpan={4}
                            className="sticky z-50 h-[22px] border border-[#145233] px-2 py-0.5 text-center text-[9px] tracking-wider text-white dark:border-emerald-950"
                            style={{ background: "#1D4D2A", minWidth: 145, width: 145, left: 28, top: 0 }}
                        >
                            ASSY NO
                        </th>
                        <th
                            className="sticky z-50 h-[22px] border border-[#145233] px-2 py-0.5 text-center text-[9px] tracking-wider text-white dark:border-emerald-950"
                            style={{ background: "#1D4D2A", minWidth: 70, width: 70, left: 173, top: 0 }}
                        >
                            Type
                        </th>
                        {groups.map((group, idx) => (
                            <th
                                key={`group-${idx}`}
                                colSpan={group.end - group.start + 1}
                                className="sticky z-40 h-[22px] border border-[#145233] px-1.5 py-0.5 text-center text-[9px] font-semibold text-white dark:border-emerald-950"
                                style={{
                                    background: group.type === 'FIRM' ? '#FFF176' : '#F6C391',
                                    color: '#1f2937',
                                    top: 0,
                                }}
                            >
                                {group.type}
                            </th>
                        ))}
                    </tr>
                    <tr>
                        <th
                            className="sticky z-50 h-[22px] border border-[#145233] px-1.5 py-0.5 text-center text-[9px] tracking-wider text-white dark:border-emerald-950"
                            style={{ background: "#1D4D2A", left: 173, minWidth: 70, width: 70, top: 22 }}
                        >
                            Month
                        </th>
                        {groups.map((group, idx) => (
                            <th
                                key={`month-${idx}`}
                                colSpan={group.end - group.start + 1}
                                className="sticky z-40 h-[22px] border border-[#145233] px-1 py-0.5 text-center text-[9px] font-semibold dark:border-emerald-950"
                                style={{
                                    background: '#DDEBC8',
                                    color: '#1f2937',
                                    top: 22
                                }}
                            >
                                {monthLabel(group.month)}
                            </th>
                        ))}
                    </tr>
                    <tr>
                        <th
                            className="sticky z-50 h-[22px] border border-[#145233] px-1.5 py-0.5 text-center text-[9px] tracking-wider text-white dark:border-emerald-950"
                            style={{ background: "#1D4D2A", left: 173, minWidth: 70, width: 70, top: 44 }}
                        >
                            ETD
                        </th>
                        {periodsWithTotals.map((p, idx) => {
                            const isRunning = isPeriodRunningWeek(p);
                            const defaultBg = p.week === 'TOT' ? '#2D5A3D' : '#1D6F42';
                            const defaultColor = '#ffffff';

                            return (
                                <th
                                    key={`etd-${idx}`}
                                    onClick={() => openDefaultPeriod(p)}
                                    title={p.week !== 'TOT' && p.etd_raw ? "Click to review ETD rows" : undefined}
                                    className={`sticky z-40 h-[22px] border border-[#145233] px-1 py-0.5 text-center text-[9px] font-semibold dark:border-emerald-950 ${p.week !== 'TOT' && p.etd_raw ? "cursor-pointer hover:ring-1 hover:ring-white/50" : ""}`}
                                    style={{
                                        background: isRunning ? "#FF0000" : defaultBg,
                                        color: isRunning ? "#000000" : defaultColor,
                                        minWidth: DEFAULT_PERIOD_WIDTH,
                                        width: DEFAULT_PERIOD_WIDTH,
                                        top: 44
                                    }}
                                >
                                    {p.week === 'TOT' ? '' : p.etd}
                                </th>
                            );
                        })}
                    </tr>
                    <tr>
                        <th
                            className="sticky z-50 h-[22px] border border-[#145233] px-1.5 py-0.5 text-center text-[9px] tracking-wider text-white dark:border-emerald-950"
                            style={{ background: "#1D4D2A", left: 173, minWidth: 70, width: 70, top: 66 }}
                        >
                            Week
                        </th>
                        {periodsWithTotals.map((p, idx) => (
                            <th
                                key={`week-${idx}`}
                                onClick={() => openDefaultPeriod(p)}
                                title={p.week !== 'TOT' && p.etd_raw ? "Click to review ETD rows" : undefined}
                                className={`sticky z-40 h-[22px] border border-[#145233] px-1 py-0.5 text-center text-[9px] font-semibold dark:border-emerald-950 ${p.week !== 'TOT' && p.etd_raw ? "cursor-pointer hover:ring-1 hover:ring-white/50" : ""}`}
                                style={{
                                    background: p.week === 'TOT' ? '#2D5A3D' : '#237A50',
                                    color: '#ffffff',
                                    minWidth: DEFAULT_PERIOD_WIDTH,
                                    width: DEFAULT_PERIOD_WIDTH,
                                    top: 66
                                }}
                            >
                                {p.week}
                            </th>
                        ))}
                    </tr>
                </thead>

                <tbody>
                    {groupedDefault.map(([assyNumber, { qty }], idx) => {
                        return (
                            <tr key={assyNumber} className="odd:bg-[#f0faf4] even:bg-[#d6efdf] dark:odd:bg-[#10251b] dark:even:bg-[#14301f]">
                                <td
                                    className="sticky left-0 z-20 border border-[#1f5f41] px-2 py-1 text-center font-bold text-white dark:border-slate-700"
                                    style={{ background: '#2D5A3D', minWidth: 28, width: 28 }}
                                >
                                    {idx + 1}
                                </td>
                                <td
                                    className="sticky z-20 truncate border border-[#1f5f41] px-2.5 py-1 font-bold text-white dark:border-slate-700"
                                    style={{ background: '#2D5A3D', left: 28, minWidth: 145, width: 145, maxWidth: 145 }}
                                    title={assyNumber}
                                >
                                    {assyNumber}
                                </td>
                                <td
                                    className="sticky z-20 border border-[#1f5f41] px-2 py-1 text-center text-[10px] font-bold dark:border-slate-700"
                                    style={{ background: '#3A6B4E', color: '#D4EDE0', left: 173, minWidth: 70, width: 70 }}
                                >
                                    QTY
                                </td>
                                {periodsWithTotals.map((p, idx) => {
                                    const key = defaultPivotKey(p.type, p.etd_raw, p.week);
                                    const val = p.week === 'TOT'
                                        ? periodKeysForTotal(p).reduce((sum, itemKey) => sum + (qty[itemKey] || 0), 0)
                                        : (qty[key] || 0);

                                    let cellBg = "transparent";
                                    let cellTextColor = "#0D4F2C";

                                    if (p.week === 'TOT') {
                                        cellBg = "rgba(125, 207, 181, 0.55)";
                                    }

                                    if (val === 0) {
                                        cellTextColor = "var(--sr-zero, #93a99b)";
                                    }

                                    return (
                                        <td
                                            key={`${key}-${idx}`}
                                            className="border border-[#b7d9c4] px-2 py-1 text-right dark:border-slate-700 dark:text-emerald-100"
                                            style={{
                                                color: cellTextColor,
                                                fontWeight: val === 0 ? 400 : 700,
                                                background: cellBg,
                                            }}
                                        >
                                            {val.toLocaleString()}
                                        </td>
                                    );
                                })}
                            </tr>
                        );
                    })}
                </tbody>

                <tfoot>
                    <tr>
                        <td
                            className="sticky left-0 z-20 border border-[#2E7D52] px-2 py-1.5 text-center font-bold text-white dark:border-slate-700"
                            style={{ background: "#0F3320" }}
                        />
                        <td
                            className="sticky z-20 border border-[#2E7D52] px-3 py-1.5 text-left font-bold text-white dark:border-slate-700"
                            style={{ background: "#0F3320", left: 28 }}
                        >
                            TOTAL
                        </td>
                        <td
                            className="sticky z-20 border border-[#2E7D52] px-2 py-1.5 dark:border-slate-700"
                            style={{ background: "#0F3320", left: 173 }}
                        />
                        {periodsWithTotals.map((p, idx) => {
                            const val = p.week === 'TOT'
                                ? groupedDefault.reduce((sum, [, { qty }]) => (
                                    sum + periodKeysForTotal(p).reduce((monthSum, key) => monthSum + (qty[key] || 0), 0)
                                ), 0)
                                : groupedDefault.reduce((sum, [, { qty }]) => {
                                    const key = defaultPivotKey(p.type, p.etd_raw, p.week);
                                    return sum + (qty[key] || 0);
                                }, 0);

                            return (
                                <td
                                    key={`total-${idx}`}
                                    className="border border-[#2E7D52] px-2 py-1.5 text-right font-bold dark:border-slate-700"
                                    style={{
                                        background: "#0F3320",
                                        color: "#ffffff"
                                    }}
                                >
                                    {val.toLocaleString()}
                                </td>
                            );
                        })}
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
