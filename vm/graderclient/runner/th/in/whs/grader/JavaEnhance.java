package th.in.whs.grader;
import java.lang.reflect.Method;
import java.lang.reflect.InvocationTargetException;
import com.google.gson.Gson;
import java.util.Scanner;

public class JavaEnhance{
	public Class cls;
	public Method main;
	public Class[] args;
	public Scanner scan = new Scanner(System.in);
	Gson gson = new Gson();

	public JavaEnhance(String className) throws ClassNotFoundException, NoSuchMethodException{
		cls = Class.forName(className);
		args = new Class[]{String.getClass()};
		Method main = cls.getMethod("main", arg);
	}

	public void runInput(){
		while(true){
			String input = scan.nextLine();
			gson
		}
	}

	public static void main(String[] args) throws ClassNotFoundException, NoSuchMethodException, IllegalAccessException, InvocationTargetException{
		if(args.length < 1){
			return;
		}
		JavaEnhance(args[0]);
	}
}

class Input{
	String input;
	String time_limit;
	String mem_limit;
}