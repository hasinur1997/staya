import { CalendarIcon, XIcon } from "lucide-react"
import * as React from "react"

import { Calendar } from "@/components/ui/calendar"
import { controlClass } from "@/components/ui/input"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { useTranslate } from "@/lib/i18n"
import { intlLocale } from "@/lib/locale"
import { cn } from "@/lib/utils"

const DAY = /^(\d{4})-(\d{2})-(\d{2})$/

/** A `Y-m-d` business date as a local Date, for the calendar grid only. */
function toDate(value: string | null | undefined): Date | undefined {
  const match = DAY.exec(value ?? "")

  return match
    ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
    : undefined
}

function toValue(date: Date): string {
  const pad = (part: number) => String(part).padStart(2, "0")

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const label = {
  format: (date: Date) =>
    new Intl.DateTimeFormat(intlLocale(), {
      day: "numeric",
      month: "short",
      year: "numeric",
    }).format(date),
}

/** Today as a `Y-m-d` date in Asia/Dhaka, whatever the viewer's clock zone. */
function dhakaToday(): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(
    new Date()
  )
}

type DatePickerProps = {
  id?: string
  /** Posts the chosen `Y-m-d` date with the surrounding form when given. */
  name?: string
  /** Controlled value (`Y-m-d`, or empty). Leave out to let the picker hold it. */
  value?: string
  defaultValue?: string
  onValueChange?: (value: string) => void
  /** Earliest and latest selectable days (`Y-m-d`). */
  min?: string | null
  max?: string | null
  placeholder?: string
  /** Shows a clear button once a date is chosen. */
  clearable?: boolean
  disabled?: boolean
  className?: string
  "aria-invalid"?: boolean
}

/**
 * The date field used everywhere: a button showing "18 Oct 2026" that
 * opens a calendar. Values stay plain `Y-m-d` strings.
 */
function DatePicker({
  id,
  name,
  value,
  defaultValue = "",
  onValueChange,
  min,
  max,
  placeholder,
  clearable = false,
  disabled,
  className,
  ...aria
}: DatePickerProps) {
  const __ = useTranslate()
  const [open, setOpen] = React.useState(false)
  const [inner, setInner] = React.useState(defaultValue)
  const current = value ?? inner
  const selected = toDate(current)
  const earliest = toDate(min)
  const latest = toDate(max)

  const change = (next: string) => {
    setInner(next)
    onValueChange?.(next)
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      {name && <input type="hidden" name={name} value={current} />}
      <div className={cn("relative", className)}>
        <PopoverTrigger asChild>
          <button
            id={id}
            type="button"
            disabled={disabled}
            data-slot="date-picker"
            className={cn(
              controlClass,
              "gap-2 text-left tabular-nums",
              clearable && selected && "pr-9"
            )}
            {...aria}
          >
            <CalendarIcon className="text-neutral size-4 shrink-0" />
            <span className={cn("truncate", !selected && "text-neutral")}>
              {selected ? label.format(selected) : (placeholder ?? __("Pick a date"))}
            </span>
          </button>
        </PopoverTrigger>
        {clearable && selected && !disabled && (
          <button
            type="button"
            aria-label={__("Clear date")}
            onClick={() => change("")}
            className="text-neutral hover:text-ink absolute top-1/2 right-2 inline-flex size-6 -translate-y-1/2 items-center justify-center rounded-sm"
          >
            <XIcon className="size-4" />
          </button>
        )}
      </div>
      <PopoverContent className="w-auto p-0">
        <Calendar
          mode="single"
          selected={selected}
          defaultMonth={selected ?? latest}
          today={toDate(dhakaToday())}
          startMonth={earliest}
          endMonth={latest}
          disabled={[
            ...(earliest ? [{ before: earliest }] : []),
            ...(latest ? [{ after: latest }] : []),
          ]}
          onSelect={(date) => {
            if (date) {
              change(toValue(date))
              setOpen(false)
            }
          }}
          autoFocus
        />
      </PopoverContent>
    </Popover>
  )
}

export { DatePicker }
