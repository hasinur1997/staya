import { CalendarIcon, ChevronLeftIcon, ChevronRightIcon, XIcon } from "lucide-react"
import * as React from "react"

import { controlClass } from "@/components/ui/input"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { useTranslate } from "@/lib/i18n"
import { intlLocale } from "@/lib/locale"
import { cn } from "@/lib/utils"

const short = {
  format: (date: Date) =>
    new Intl.DateTimeFormat(intlLocale(), { month: "short", timeZone: "UTC" }).format(date),
}
const long = {
  format: (date: Date) =>
    new Intl.DateTimeFormat(intlLocale(), {
      month: "long",
      year: "numeric",
      timeZone: "UTC",
    }).format(date),
}
const MONTHS = Array.from({ length: 12 }, (_, index) => index)

type MonthPickerProps = {
  id?: string
  /** `Y-m`, or empty for no month. */
  value: string
  onValueChange: (value: string) => void
  placeholder?: string
  clearable?: boolean
  className?: string
}

/**
 * The month field used everywhere: a button showing "October 2026" that
 * opens a year stepper over a grid of twelve months.
 */
function MonthPicker({
  id,
  value,
  onValueChange,
  placeholder,
  clearable = true,
  className,
}: MonthPickerProps) {
  const __ = useTranslate()
  const [open, setOpen] = React.useState(false)
  const [chosenYear, chosenMonth] = value
    ? value.split("-").map(Number)
    : [null, null]
  const thisYear = Number(
    new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka", year: "numeric" }).format(new Date())
  )
  const [year, setYear] = React.useState(chosenYear ?? thisYear)
  const step =
    "text-ink-muted hover:bg-surface-raised hover:text-ink inline-flex size-8 items-center justify-center rounded-sm transition-colors"

  return (
    <Popover
      open={open}
      onOpenChange={(next) => {
        setOpen(next)
        setYear(chosenYear ?? thisYear)
      }}
    >
      <div className={cn("relative", className)}>
        <PopoverTrigger asChild>
          <button
            id={id}
            type="button"
            data-slot="month-picker"
            className={cn(controlClass, "gap-2 text-left", clearable && value && "pr-9")}
          >
            <CalendarIcon className="text-neutral size-4 shrink-0" />
            <span className={cn("truncate", !value && "text-neutral")}>
              {chosenYear && chosenMonth
                ? long.format(new Date(Date.UTC(chosenYear, chosenMonth - 1, 1)))
                : (placeholder ?? __("Any month"))}
            </span>
          </button>
        </PopoverTrigger>
        {clearable && value && (
          <button
            type="button"
            aria-label={__("Clear month")}
            onClick={() => onValueChange("")}
            className="text-neutral hover:text-ink absolute top-1/2 right-2 inline-flex size-6 -translate-y-1/2 items-center justify-center rounded-sm"
          >
            <XIcon className="size-4" />
          </button>
        )}
      </div>
      <PopoverContent className="w-64 p-3">
        <div className="flex items-center justify-between">
          <button type="button" aria-label={__("Previous year")} className={step} onClick={() => setYear(year - 1)}>
            <ChevronLeftIcon className="size-4" />
          </button>
          <span className="text-label tabular-nums">{year}</span>
          <button type="button" aria-label={__("Next year")} className={step} onClick={() => setYear(year + 1)}>
            <ChevronRightIcon className="size-4" />
          </button>
        </div>
        <div className="mt-3 grid grid-cols-3 gap-1">
          {MONTHS.map((month) => {
            const active = year === chosenYear && month + 1 === chosenMonth

            return (
              <button
                key={month}
                type="button"
                aria-pressed={active}
                onClick={() => {
                  onValueChange(`${year}-${String(month + 1).padStart(2, "0")}`)
                  setOpen(false)
                }}
                className={cn(
                  "hover:bg-surface-raised h-9 rounded-sm text-body transition-colors",
                  active && "bg-primary text-on-primary hover:bg-primary-hover font-medium"
                )}
              >
                {short.format(new Date(Date.UTC(year, month, 1)))}
              </button>
            )
          })}
        </div>
      </PopoverContent>
    </Popover>
  )
}

export { MonthPicker }
