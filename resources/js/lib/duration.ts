/** The unit a duration field is typed in. */
export type DurationUnit = 'seconds' | 'minutes' | 'days';

const SECONDS_PER: Record<DurationUnit, number> = {
    seconds: 1,
    minutes: 60,
    days: 86400,
};

/**
 * The scale a duration is read on, largest first.
 *
 * Weeks and months are deliberately absent: "6 weeks" and "3 months" are both
 * ambiguous readings of a token lifetime, where days are what an operator
 * actually reasons about.
 */
const SCALE: { seconds: number; one: string; many: string }[] = [
    { seconds: 31536000, one: 'year', many: 'years' },
    { seconds: 86400, one: 'day', many: 'days' },
    { seconds: 3600, one: 'hr', many: 'hr' },
    { seconds: 60, one: 'min', many: 'min' },
    { seconds: 1, one: 'sec', many: 'sec' },
];

const count = (total: number, step: (typeof SCALE)[number]): string => {
    const whole = Math.floor(total / step.seconds);

    return `${whole} ${whole === 1 ? step.one : step.many}`;
};

/**
 * What a duration comes to, in the largest unit that fits.
 *
 * Answers null when the reading would only repeat what was typed — 90 in a
 * field of days is already "90 days" — so it appears exactly when it has
 * something to add.
 *
 * @param value  the number in the field
 * @param unit   what that field counts
 */
export function humanizeDuration(
    value: number,
    unit: DurationUnit,
): string | null {
    const total = Math.round(value * SECONDS_PER[unit]);

    if (!Number.isFinite(total) || total < 1) {
        return null;
    }

    const step = SCALE.findIndex((candidate) => total >= candidate.seconds);
    const remainder = total % SCALE[step].seconds;

    if (remainder === 0 && SCALE[step].seconds === SECONDS_PER[unit]) {
        return null;
    }

    const rest = SCALE.find(
        (candidate, index) => index > step && remainder >= candidate.seconds,
    );

    return rest === undefined
        ? count(total, SCALE[step])
        : `${count(total, SCALE[step])} ${count(remainder, rest)}`;
}
