import {
    createContext,
    useContext,
    useEffect,
    useMemo,
    useState,
    type Dispatch,
    type ReactNode,
    type SetStateAction,
} from 'react';

type Theme = 'light' | 'dark';

interface ThemeContextValue {
    theme: Theme;
    isDark: boolean;
    setTheme: Dispatch<SetStateAction<Theme>>;
    toggleTheme: () => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

const getInitialTheme = (): Theme => {
    if (typeof window === 'undefined') {
        return 'light';
    }

    const savedTheme = window.localStorage.getItem('theme');
    if (savedTheme === 'dark' || savedTheme === 'light') {
        return savedTheme;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [theme, setTheme] = useState<Theme>(getInitialTheme);

    useEffect(() => {
        const root = window.document.documentElement;

        root.classList.toggle('dark', theme === 'dark');
        root.style.colorScheme = theme;
        window.localStorage.setItem('theme', theme);
    }, [theme]);

    const value = useMemo(() => ({
        theme,
        isDark: theme === 'dark',
        setTheme,
        toggleTheme: () => setTheme((current) => (current === 'dark' ? 'light' : 'dark')),
    }), [theme]);

    return (
        <ThemeContext.Provider value={value}>
            {children}
        </ThemeContext.Provider>
    );
}

export const useTheme = () => {
    const context = useContext(ThemeContext);

    if (!context) {
        throw new Error('useTheme harus dipakai di dalam ThemeProvider');
    }

    return context;
};
