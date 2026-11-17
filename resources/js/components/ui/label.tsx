import * as LabelPrimitive from "@radix-ui/react-label"
import * as React from "react"

import { cn } from "@/lib/utils"

type LabelProps = React.ComponentProps<typeof LabelPrimitive.Root> & {
  /** Shows the red * for a field the form requires. Native inputs marked `required` are detected without it. */
  required?: boolean
}

/** Whether the control this label points at is a native field marked `required`. */
function controlIsRequired(htmlFor: string | undefined): boolean {
  if (!htmlFor) {
    return false
  }

  const control = document.getElementById(htmlFor)

  return (
    control instanceof HTMLInputElement ||
    control instanceof HTMLTextAreaElement ||
    control instanceof HTMLSelectElement
  )
    ? control.required
    : false
}

function Label({ className, children, required, ...props }: LabelProps) {
  const [detected, setDetected] = React.useState(false)

  React.useLayoutEffect(() => {
    setDetected(controlIsRequired(props.htmlFor))
  })

  return (
    <LabelPrimitive.Root
      data-slot="label"
      className={cn(
        "field-label select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
        className
      )}
      {...props}
    >
      {props.asChild ? (
        children
      ) : (
        <>
          {children}
          {(required || detected) && (
            <span className="ml-1 text-error" aria-hidden="true">
              *
            </span>
          )}
        </>
      )}
    </LabelPrimitive.Root>
  )
}

export { Label }
