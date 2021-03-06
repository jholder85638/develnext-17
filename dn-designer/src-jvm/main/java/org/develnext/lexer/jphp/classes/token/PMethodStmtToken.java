package org.develnext.lexer.jphp.classes.token;

import org.develnext.jphp.core.tokenizer.TokenMeta;
import org.develnext.jphp.core.tokenizer.token.stmt.MethodStmtToken;
import org.develnext.lexer.jphp.DevelNextLexerExtension;
import php.runtime.annotation.Reflection;
import php.runtime.annotation.Reflection.Signature;
import php.runtime.env.Environment;
import php.runtime.reflection.ClassEntity;

@Reflection.Name("MethodStmtToken")
@Reflection.Namespace(DevelNextLexerExtension.NS + "\\token")
public class PMethodStmtToken extends PFunctionStmtToken<MethodStmtToken> {
    interface WrappedInterface {
        boolean isFinal();
        boolean isAbstract();
        boolean isInterfacable();
        boolean isStatic();
    }

    public PMethodStmtToken(Environment env, MethodStmtToken wrappedObject) {
        super(env, wrappedObject);
    }

    public PMethodStmtToken(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Signature
    public void __construct() {
        __wrappedObject = new MethodStmtToken(TokenMeta.empty());
    }

    @Signature
    public String getOwnerName() {
        return getWrappedObject().getClazz().getFulledName();
    }

    @Signature
    public PClassStmtToken getOwner(Environment env) {
        return new PClassStmtToken(env, getWrappedObject().getClazz());
    }

}
