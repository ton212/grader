package th.in.whs.grader;
import java.util.LinkedList;
import java.lang.reflect.Method;
import java.lang.reflect.InvocationTargetException;
import com.google.gson.Gson;

public class JavaInput{
	public static void main(String[] args) throws ClassNotFoundException, NoSuchMethodException, IllegalAccessException, InvocationTargetException{
		if(args.length < 1){
			System.out.println("[]");
			return;
		}
		LinkedList<String> out = new LinkedList<String>();
		Class generator = Class.forName(args[0]);
		Class[] arg = new Class[]{out.getClass()};
		Method run = generator.getMethod("run", arg);
		run.invoke(null, out);
		Gson gson = new Gson();
		System.out.println(gson.toJson(out));
	}
}