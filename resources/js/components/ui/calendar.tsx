import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react"
import * as React from "react"
import { DayPicker } from "react-day-picker"
import { bn, enGB } from "react-day-picker/locale"

import { getActiveLocale } from "@/lib/locale"

import { cn } from "@/lib/utils"

const navButton =
  "text-ink-muted hover:bg-surface-raised hover:text-ink inline-flex size-8 items-center justify-center rounded-sm transition-colors disabled:pointer-events-none disabled:opacity-40"

/** A month grid for picking one day. Used through DatePicker. */
function Calendar({
  className,
  classNames,
  ...props
}: React.ComponentProps<typeof DayPicker>) {
  return (
    <DayPicker
      locale={getActiveLocale() === "bn" ? bn : enGB}
      showOutsideDays
      weekStartsOn={6}
      className={cn("p-3", className)}
      classNames={{
        root: "relative",
        months: "flex flex-col",
        month: "flex flex-col gap-3",
        month_caption: "flex h-8 items-center justify-center",
        caption_label: "text-label",
        nav: "absolute inset-x-0 top-0 flex items-center justify-between",
        button_previous: navButton,
        button_next: navButton,
        month_grid: "border-collapse",
        weekdays: "flex",
        weekday: "text-ink-muted w-9 text-caption font-normal",
        week: "mt-1 flex",
        day: "size-9 p-0 text-center",
        day_button:
          "hover:bg-surface-raised inline-flex size-9 items-center justify-center rounded-sm text-small tabular-nums transition-colors",
        today: "[&>button]:ring-1 [&>button]:ring-inset [&>button]:ring-primary",
        selected:
          "[&>button]:bg-primary [&>button]:text-on-primary [&>button]:hover:bg-primary-hover [&>button]:font-medium",
        outside: "[&>button]:text-neutral",
        disabled: "[&>button]:pointer-events-none [&>button]:opacity-40",
        hidden: "invisible",
        ...classNames,
      }}
      components={{
        Chevron: ({ orientation }) =>
          orientation === "left" ? (
            <ChevronLeftIcon className="size-4" />
          ) : (
            <ChevronRightIcon className="size-4" />
          ),
      }}
      {...props}
    />
  )
}

export { Calendar }
