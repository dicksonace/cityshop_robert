import { useRef } from 'react';

type Props = {
    value: string;
    onChange: (value: string) => void;
    id?: string;
    disabled?: boolean;
};

export default function OtpCodeInput({ value, onChange, id, disabled }: Props) {
    const refs = useRef<Array<HTMLInputElement | null>>([]);
    const digits = Array.from({ length: 6 }, (_, index) => value.replace(/\D/g, '')[index] ?? '');

    function commit(nextDigits: string[], focus: number) {
        onChange(nextDigits.join('').replace(/\D/g, '').slice(0, 6));
        const index = Math.max(0, Math.min(5, focus));
        requestAnimationFrame(() => refs.current[index]?.focus());
    }

    return (
        <div className="mt-2 flex justify-between gap-2" role="group" aria-label="6-digit code">
            {digits.map((digit, index) => (
                <input
                    key={index}
                    id={index === 0 ? id : undefined}
                    ref={(element) => {
                        refs.current[index] = element;
                    }}
                    value={digit}
                    disabled={disabled}
                    inputMode="numeric"
                    autoComplete={index === 0 ? 'one-time-code' : 'off'}
                    aria-label={`Digit ${index + 1}`}
                    maxLength={index === 0 ? 6 : 1}
                    className="h-14 w-11 rounded-xl border border-gray-200 bg-white text-center text-xl font-bold text-gray-900 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-200 sm:w-12"
                    onChange={(event) => {
                        const clean = event.target.value.replace(/\D/g, '');
                        if (!clean) {
                            const next = [...digits];
                            next[index] = '';
                            commit(next, index);
                            return;
                        }
                        const next = [...digits];
                        clean
                            .slice(0, 6 - index)
                            .split('')
                            .forEach((char, offset) => {
                                next[index + offset] = char;
                            });
                        commit(next, Math.min(index + clean.length, 5));
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'Backspace' && !digits[index] && index > 0) {
                            event.preventDefault();
                            const next = [...digits];
                            next[index - 1] = '';
                            commit(next, index - 1);
                        }
                        if (event.key === 'ArrowLeft' && index > 0) {
                            event.preventDefault();
                            refs.current[index - 1]?.focus();
                        }
                        if (event.key === 'ArrowRight' && index < 5) {
                            event.preventDefault();
                            refs.current[index + 1]?.focus();
                        }
                    }}
                    onPaste={(event) => {
                        const text = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
                        if (!text) return;
                        event.preventDefault();
                        commit(
                            Array.from({ length: 6 }, (_, slot) => text[slot] ?? ''),
                            Math.min(text.length, 5),
                        );
                    }}
                />
            ))}
        </div>
    );
}
