import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * The one look for every form control (inputs, textareas, comboboxes and
 * date pickers): surface fill, 1px outline, 12px radius; focus fades in a soft
 * primary glow with a faint border tint, never a hard outline.
 */
const controlClass =
  "border-input bg-surface text-ink placeholder:text-neutral selection:bg-primary selection:text-on-primary flex h-10 w-full min-w-0 items-center rounded-md border px-3 text-body transition-[color,border-color,box-shadow] duration-200 ease-out outline-none hover:border-neutral/60 focus-visible:border-primary/40 focus-visible:shadow-focus focus-visible:outline-none data-[state=open]:border-primary/40 data-[state=open]:shadow-focus disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 read-only:bg-surface-raised aria-invalid:border-destructive"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        controlClass,
        "file:text-ink file:mr-3 file:inline-flex file:h-full file:border-0 file:bg-transparent file:text-label",
        className
      )}
      {...props}
    />
  )
}

export { Input, controlClass }
