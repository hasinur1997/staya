export type MealTypeOption = { id: number; name: string; is_active: boolean };

export type MealDayMember = {
    id: number;
    name: string;
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

export type MealType = {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
    has_meals: boolean;
};
