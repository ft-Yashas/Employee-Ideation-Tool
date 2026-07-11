import { createContext, useContext, useState, useCallback } from 'react';
import { SUPPORTED_LANGS, getT } from '../i18n/translations';

const LangContext = createContext(null);

export function LangProvider({ children }) {
  const stored = localStorage.getItem('ifqm-lang') || 'en';
  const [lang, setLangState] = useState(SUPPORTED_LANGS.includes(stored) ? stored : 'en');
  const t = getT(lang);

  const setLang = useCallback((l) => {
    if (!SUPPORTED_LANGS.includes(l)) return;
    setLangState(l);
    localStorage.setItem('ifqm-lang', l);
  }, []);

  return (
    <LangContext.Provider value={{ lang, setLang, t }}>
      {children}
    </LangContext.Provider>
  );
}

export function useLang() {
  return useContext(LangContext);
}
