import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/react'
import { Children, isValidElement, type ChangeEvent, type ReactNode } from 'react'

export type SelectOption = { value: string; label: string; disabled?: boolean }

type SelectProps = {
  value: string
  onChange: (event: ChangeEvent<HTMLSelectElement>) => void
  children: ReactNode
  options?: unknown
  className?: string
  disabled?: boolean
  'data-testid'?: string
}

export function Select({ value, onChange, children, className = '', disabled = false, 'data-testid': testId }: SelectProps) {
  const options = Children.toArray(children).flatMap((child) => {
    if (!isValidElement<{ value?: string | number; disabled?: boolean; children?: ReactNode }>(child)) return []
    return [{ value: String(child.props.value ?? ''), label: String(child.props.children ?? ''), disabled: child.props.disabled }]
  })
  const selected = options.find((option) => option.value === value)
  return (
    <Listbox value={value} onChange={(nextValue: string) => onChange({ target: { value: nextValue } } as ChangeEvent<HTMLSelectElement>)} disabled={disabled}>
      <div className="relative">
        <ListboxButton data-testid={testId} className={`flex w-full items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-left text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20 disabled:opacity-60 ${className}`}>
          <span>{selected?.label ?? 'Selecione'}</span><span className="text-cyan-200">⌄</span>
        </ListboxButton>
        <ListboxOptions anchor="bottom start" className="z-50 mt-2 max-h-64 w-[var(--button-width)] overflow-auto rounded-2xl border border-white/10 bg-slate-950 p-1 shadow-2xl shadow-black/50 focus:outline-none">
          {options.map((option) => <ListboxOption key={option.value} value={option.value} disabled={option.disabled} className="cursor-pointer rounded-xl px-3 py-2 text-sm text-slate-100 data-[focus]:bg-cyan-400/20 data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50">
            {option.label}
          </ListboxOption>)}
        </ListboxOptions>
      </div>
    </Listbox>
  )
}
