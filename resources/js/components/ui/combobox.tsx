import { CheckIcon, ChevronsUpDownIcon } from "lucide-react"
import * as React from "react"

import {
  Command,
  CommandEmpty,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command"
import { controlClass } from "@/components/ui/input"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"

type ComboboxOption = { value: string; label: string }

type ComboboxProps = {
  id?: string
  /** Posts the chosen value with the surrounding form when given. */
  name?: string
  value: string
  onValueChange: (value: string) => void
  options: ComboboxOption[]
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  disabled?: boolean
  className?: string
  "aria-invalid"?: boolean
  "aria-label"?: string
}

/**
 * The dropdown used everywhere: a button that opens a searchable list.
 * Type to filter, arrow keys to move, Enter to choose.
 */
function Combobox({
  id,
  name,
  value,
  onValueChange,
  options,
  placeholder = "Choose",
  searchPlaceholder = "Search",
  emptyText = "Nothing found.",
  disabled,
  className,
  ...aria
}: ComboboxProps) {
  const [open, setOpen] = React.useState(false)
  const selected = options.find((option) => option.value === value)

  return (
    <Popover open={open} onOpenChange={setOpen}>
      {name && <input type="hidden" name={name} value={value} />}
      <PopoverTrigger asChild>
        <button
          id={id}
          type="button"
          role="combobox"
          aria-expanded={open}
          aria-haspopup="listbox"
          disabled={disabled}
          data-slot="combobox"
          className={cn(controlClass, "justify-between gap-2 text-left", className)}
          {...aria}
        >
          <span className={cn("truncate", !selected && "text-neutral")}>
            {selected ? selected.label : placeholder}
          </span>
          <ChevronsUpDownIcon className="text-neutral size-4 shrink-0" />
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-(--radix-popover-trigger-width) min-w-48 p-0">
        <Command>
          <CommandInput placeholder={searchPlaceholder} />
          <CommandList>
            <CommandEmpty>{emptyText}</CommandEmpty>
            {options.map((option) => (
              <CommandItem
                key={option.value}
                value={`${option.label} ${option.value}`}
                onSelect={() => {
                  onValueChange(option.value)
                  setOpen(false)
                }}
              >
                <span className="flex-1 truncate">{option.label}</span>
                {option.value === value && (
                  <CheckIcon className="text-primary" />
                )}
              </CommandItem>
            ))}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

export { Combobox }
export type { ComboboxOption }
