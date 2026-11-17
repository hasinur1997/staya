# Staya (Skylearn-based)

## Overview

A bright, friendly and professional design system for a mess management tool. A clear blue anchors the brand, surfaces are generous and white, corners are softly rounded and type is a single clean family, Roboto. It adapts the Skylearn system's look (colours, rounded corners, soft shadows, input style, accessibility stance) to a finance tool: compact sizes, dense tables, no illustration or gamification. Light and dark modes both feel intentional.

## Colors

- **Primary** (#2563EB): Blue — buttons, links, active states, focus rings, progress. Slightly deeper than Skylearn's sky (#3B82F6) so white text on it passes 4.5:1
- **Primary Hover** (#1D4ED8): Pressed and hover states of primary elements
- **Accent / Highlight** (#FBBF24): Sun yellow — counts, badges and attention only, never primary actions
- **Neutral** (#64748B): Muted text, placeholders, timestamps, metadata
- **Background** (#FFFFFF): Page background
- **Surface** (#F8FAFC): Cards, panels, inputs
- **Surface Raised** (#F1F5F9): Hover states, selected rows, inset wells, read-only fields
- **Text Primary** (#0F172A): Deep navy near-black — headings, body text, labels
- **Text Secondary** (#475569): Descriptions, captions, secondary info
- **Border** (#E2E8F0): Card edges, dividers, input borders
- **Success** (#15803D): Approved, credit, positive states (Skylearn leaf, deepened for text contrast)
- **Warning** (#B45309): Needs attention, caution banners
- **Error** (#DC2626): Failed, due, destructive actions

Dark mode keeps the same slate family: background #0F172A, surface #1E293B, raised #334155, border #475569, text #F8FAFC / #CBD5E1, primary #60A5FA.

## Typography

- **Display and Body Font**: Roboto, regular (400) and medium (500) only, loaded from Google Fonts
- **Code Font**: Roboto Mono
- **Bangla and the ৳ sign**: Hind Siliguri as fallback

Headings use Roboto medium with slightly tight letter spacing (-0.01em). Body and UI text use regular; labels, subheads and buttons use medium. Money uses tabular figures.

Type scale: Display 64px, Headline 48px, Section heading 28px, Subhead 20px, Body 16px, Small 14px, Caption 12px, Overline 11px uppercase tracking-wide.

## Elevation

Cards rest flat with a 1px border (#E2E8F0) and gain a soft shadow on hover (0 8px 24px rgba(15,23,42,0.06)). Primary buttons gain a blue glow on hover (0 8px 20px rgba(37,99,235,0.22)). Modals and popovers use a larger shadow (0 24px 48px rgba(15,23,42,0.12)) with a backdrop blur. The nav is transparent with backdrop-blur, gaining a 1px bottom border on scroll.

## Components

- **Buttons**: Primary uses the blue fill with white text, 12px radius, medium weight. Secondary has a 1px border. Ghost has no border. Destructive uses the error colour. 150ms transition. Sizes: small (32px), medium (40px), large (48px).
- **Cards**: Surface fill, 1px border, 20px radius, 16px padding. Hover adds the card shadow.
- **Inputs**: One shared control style for text inputs, textareas, comboboxes and date pickers: 1px border, surface background, 12px radius, 40px height, 16px text. Hover darkens the border slightly; focus fades in a soft 3px primary glow with a faint border tint and no hard outline. Error turns the border red with the message below. Labels are 14px medium above the control.
- **Dropdowns**: Always the searchable `Combobox` (type to filter, arrow keys, Enter). Never a native or non-searchable select.
- **Dates**: Always `DatePicker` (calendar popover, shows "18 Oct 2026", week starts Saturday, today highlighted, min/max respected) or `MonthPicker` (year stepper over twelve months). Never a native date input.
- **Chips**: Pill-shaped. Status chips always show their word, never colour alone.
- **Progress Bars**: 8px height, rounded-full, surface-raised track, primary fill.
- **Avatars**: Circular, 32px default.
- **Tabs**: Horizontal, underline style. Active: primary text with 2px bottom border.
- **Navigation**: Sidebar, 256px. Active item: 3px primary left bar with a primary-tinted background.

## Spacing

- Base unit: 4px
- Scale: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80px
- Component padding: small 8x12, medium 12x16, large 16x24
- Section spacing: 24px mobile, 32px tablet, 48px desktop
- Container max width: 1200px with 24px horizontal padding
- Card grid gap: 16px mobile, 24px desktop

## Border Radius

- 8px: chips, small badges, calendar days, menu items
- 12px: buttons, inputs, comboboxes, date pickers
- 20px: cards, panels, modals, popovers
- 9999px: avatars, pills, progress bars

## Accessibility

Contrast targets WCAG AA (4.5:1) on all text. Colour is never the only signal: every status shows its word. Focus is always visible (primary outline). Controls are at least 40px tall; touch layouts keep targets comfortable. Motion respects prefers-reduced-motion.

## Do's and Don'ts

- Do use the primary blue only for interactive elements and active states
- Do keep one primary button per view
- Do use Roboto only, at regular and medium weights
- Do build every dropdown from `Combobox` and every date from `DatePicker` / `MonthPicker`
- Do use the highlight yellow sparingly, for attention only
- Don't use more than two font weights on a screen
- Don't use raw hex colours, arbitrary pixel values or Tailwind's default palette in components; use the tokens
- Don't add decorative elements, illustrations, confetti or emoji
- Don't mix radius values: buttons and inputs 12px, cards 20px
