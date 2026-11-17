import { Command as CommandPrimitive } from "cmdk"
import { SearchIcon } from "lucide-react"
import * as React from "react"

import { cn } from "@/lib/utils"

function Command({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive>) {
  return (
    <CommandPrimitive
      data-slot="command"
      className={cn("flex w-full flex-col overflow-hidden", className)}
      {...props}
    />
  )
}

function CommandInput({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Input>) {
  return (
    <div className="flex h-10 items-center gap-2 border-b px-3">
      <SearchIcon className="text-neutral size-4 shrink-0" />
      <CommandPrimitive.Input
        data-slot="command-input"
        className={cn(
          "placeholder:text-neutral h-full w-full bg-transparent text-caption outline-hidden focus-visible:outline-none disabled:opacity-50",
          className
        )}
        {...props}
      />
    </div>
  )
}

function CommandList({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.List>) {
  return (
    <CommandPrimitive.List
      data-slot="command-list"
      className={cn("max-h-64 scroll-py-1 overflow-x-hidden overflow-y-auto p-1", className)}
      {...props}
    />
  )
}

function CommandEmpty({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Empty>) {
  return (
    <CommandPrimitive.Empty
      data-slot="command-empty"
      className={cn("text-ink-muted px-3 py-4 text-center text-caption", className)}
      {...props}
    />
  )
}

function CommandItem({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Item>) {
  return (
    <CommandPrimitive.Item
      data-slot="command-item"
      className={cn(
        "data-[selected=true]:bg-primary-soft relative flex min-h-9 cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-body outline-hidden select-none data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0",
        className
      )}
      {...props}
    />
  )
}

export { Command, CommandEmpty, CommandInput, CommandItem, CommandList }
