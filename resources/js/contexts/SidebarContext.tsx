import { createContext, useContext, useEffect, useState, type Dispatch, type ReactNode, type SetStateAction } from 'react';

type SidebarContextValue = {
    sidebarOpen: boolean;
    isMobileOpen: boolean;
    isHovered: boolean;
    activeItem: string | null;
    openSubmenu: string | null;
    isMobile: boolean;
    toggleSidebar: () => void;
    toggleMobileSidebar: () => void;
    setIsHovered: Dispatch<SetStateAction<boolean>>;
    setActiveItem: Dispatch<SetStateAction<string | null>>;
    toggleSubmenu: (menu: string) => void;
    setSidebarOpen: Dispatch<SetStateAction<boolean>>;
};

export const SidebarContext = createContext<SidebarContextValue | null>(null);

export default function SidebarProvider({ children }: { children: ReactNode }) {
    const [sidebarOpen, setSidebarOpen] = useState(() => {
        if (typeof window === 'undefined') return true;
        const saved = localStorage.getItem('sidebarOpen');
        return saved !== null ? saved === 'true' : window.innerWidth >= 1024;
    });

    const [isMobileOpen, setIsMobileOpen] = useState(false);
    const [isHovered, setIsHovered] = useState(false);
    const [activeItem, setActiveItem] = useState<string | null>(null);
    const [openSubmenu, setOpenSubmenu] = useState<string | null>(null);
    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        if (typeof window !== 'undefined') {
            localStorage.setItem('sidebarOpen', String(sidebarOpen));
        }
    }, [sidebarOpen]);

    useEffect(() => {
        const handleResize = () => {
            const mobile = window.innerWidth < 768;
            setIsMobile(mobile);

            if (mobile) {
                setSidebarOpen(false);
            } else {
                setIsMobileOpen(false);
            }
        };

        handleResize();
        window.addEventListener('resize', handleResize);

        return () => window.removeEventListener('resize', handleResize);
    }, []);

    const toggleSidebar = () => setSidebarOpen((prev) => !prev);
    const toggleMobileSidebar = () => setIsMobileOpen((prev) => !prev);
    const toggleSubmenu = (menu: string) => {
        setOpenSubmenu((prev) => (prev === menu ? null : menu));
    };

    return (
        <SidebarContext.Provider
            value={{
                sidebarOpen: isMobile ? false : sidebarOpen,
                isMobileOpen,
                isHovered,
                activeItem,
                openSubmenu,
                isMobile,
                toggleSidebar,
                toggleMobileSidebar,
                setIsHovered,
                setActiveItem,
                toggleSubmenu,
                setSidebarOpen,
            }}
        >
            {children}
        </SidebarContext.Provider>
    );
}

export const useSidebar = () => {
    const context = useContext(SidebarContext);

    if (!context) {
        throw new Error('useSidebar harus dipakai di dalam SidebarProvider');
    }

    return context;
};
