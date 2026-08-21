export type SharedPageProps = {
  auth: {
    user: null | {
      id: number | string;
      name: string;
      email: string;
    };
    permissions: string[];
  };
  tenant: {
    company: null;
    branch: null;
  };
  locale: 'en' | 'ar' | string;
  direction: 'ltr' | 'rtl';
  theme: 'light' | 'dark' | string;
  notifications: {
    unreadCount: number;
  };
  flash: {
    success?: string | null;
    error?: string | null;
  };
};
