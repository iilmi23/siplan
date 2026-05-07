import { Link } from "@inertiajs/react";
import { ChevronRightIcon } from "@heroicons/react/24/outline";

export default function Breadcrumb({ items = [] }) {
    return (
        <nav className="mb-4 flex flex-wrap items-center gap-2 text-sm" aria-label="Breadcrumb">
            <span className="text-gray-600">Menu</span>
            {items.map((item, index) => {
                const isLast = index === items.length - 1;

                return (
                    <div key={`${item.label}-${index}`} className="flex items-center gap-2">
                        <ChevronRightIcon className="h-4 w-4 text-gray-400" />
                        {item.href && !isLast ? (
                            <Link href={item.href} className="text-gray-600 transition-colors hover:text-[#1D6F42]">
                                {item.label}
                            </Link>
                        ) : (
                            <span className={isLast ? "font-medium text-gray-800" : "text-gray-600"}>
                                {item.label}
                            </span>
                        )}
                    </div>
                );
            })}
        </nav>
    );
}
