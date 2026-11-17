import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-sm border px-2 py-0.5 text-caption font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90",
        secondary:
          "border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90",
        destructive:
          "border-transparent bg-destructive text-on-primary [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60",
        outline:
          "text-ink [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
        // Statuses: tinted background, the word carries the meaning.
        neutral:
          "rounded-pill border-transparent bg-surface-raised px-2.5 font-medium text-ink before:size-1.5 before:rounded-full before:bg-neutral before:content-['']",
        success:
          "rounded-pill border-transparent bg-success-soft px-2.5 font-medium text-ink before:size-1.5 before:rounded-full before:bg-success before:content-['']",
        warning:
          "rounded-pill border-transparent bg-warning-soft px-2.5 font-medium text-ink before:size-1.5 before:rounded-full before:bg-warning before:content-['']",
        error:
          "rounded-pill border-transparent bg-error-soft px-2.5 font-medium text-ink before:size-1.5 before:rounded-full before:bg-error before:content-['']",
        // Counts on nav items and menus (amber accent).
        count:
          "rounded-pill border-transparent bg-highlight px-2 text-ink tabular-nums",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
