export type MealTypeOption = { id: number; name: string; is_active: boolean };

export type MealDayMember = {
    id: number;
    name: string;
    has_photo: boolean;
    media_version: number | null;
    /** Meal type id to count; missing means none. */
    counts: Record<string, number>;
};

export type MealMonthFigures = {
    meals: number;
    expenses: string;
    /** Expenses divided by meals; null before any meal is recorded. */
    rate: string | null;
    split: 'equal' | 'meals';
};

export type MealSheetDay = { day: number; weekday: number; total: number };

export type MealSheetRow = {
    member_id: number;
    name: string;
    has_photo: boolean;
    media_version: number | null;
    /** Day of month to meals that day; missing means none. */
    counts: Record<string, number>;
    total: number;
    /** Money; null when a single meal type is shown. */
    cost: string | null;
};

export type MealType = {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
    has_meals: boolean;
};
